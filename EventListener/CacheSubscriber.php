<?php

namespace Parabol\CacheBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Symfony\Component\Yaml\Yaml;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Parabol\CacheBundle\Mapper\EntityMapper;

class CacheSubscriber implements EventSubscriberInterface 
{
	const SUBCACHE_DIR = '/parabol/cachebundle/';
    const CACHE_VIEWS_DIR = 'views/';
    const CACHE_MAPS_DIR = 'maps/';

    private $kernel;
    private $currentName = null;
    private $handler;
	private $excluded;
    private $excludedPattern;
    private $map = [];
    
    private $cachedMap;
    private $minifierCommand;
    private $minifierCommandParams;
    private $cacheDev;
    private $cacheDir;  


	public function __construct($kernel, FragmentHandler $handler, $minifierCommand, $minifierCommandParams, $excluded, $excludedPattern, $cacheDev)
	{
        $this->kernel = $kernel;
        $this->handler = $handler;
		$this->minifierCommand = $minifierCommand;
        $this->minifierCommandParams = $minifierCommandParams;
        $this->cacheDev = $cacheDev;
        $this->excluded = $excluded;
        $this->excludedPattern = $excludedPattern;
        $this->cachedMap = new EntityMapper();


        $this->cacheDir = $this->kernel->getCacheDir() . CacheSubscriber::SUBCACHE_DIR;
		

	}

	public static function getSubscribedEvents()
    {
        // return the subscribed events, their methods and priorities
        return array(
           KernelEvents::REQUEST => array('onKernelRequest', 7),
           KernelEvents::RESPONSE => 'onKernelResponse',
           // KernelEvents::CONTROLLER => 'onKernelController'
        );
    }

    public function getCurrentName()
    {
        return $this->currentName;
    }

    private function isAllowedToProccess(\Symfony\Component\HttpKernel\Event\KernelEvent $event)
    {   
        $request = $event->getRequest();
        if(!$request->isMethod(Request::METHOD_GET) || $request->isXmlHttpRequest() && $request->get('nocache') || $this->kernel->getEnvironment() == 'dev' && !$this->cacheDev) return false;
        else return $request->getPathInfo() == '/_fragment' || $event->isMasterRequest() && !preg_match('/' . $this->excludedPattern . '/', $request->getPathInfo());
    }

   
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if($this->isAllowedToProccess($event))
        {
            $this->currentName = $this->getName($event, '');
            $this->cachedMap->addState($this->currentName, '');
        }
        else 
        {
            return;
        }

        $ext = ($this->minifierCommand && $this->minifierCommandParams ? '.min' : '');

        if($request->attributes->get('cache') == EntityMapper::excluded)
        {
             $this->cachedMap->addState($this->currentName, EntityMapper::perform);
        }
        elseif(isset($this->excluded[$this->getActionShort($request)]))
        {
            $this->cachedMap->addState($this->currentName, EntityMapper::excluded);
        }
        elseif(file_exists($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $this->currentName . $ext))
        {
            $this->cachedMap->addState($this->currentName, EntityMapper::cached);
            $content = file_get_contents($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $this->currentName . $ext);

            if(preg_match_all('/___(master|fragment)__([\w_]+)__([\w_=]+)___/', $content, $matches))
            {
                foreach($matches[0] as $i => $match){
                    if(file_exists($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $match . $ext))
                    {
                        $content = preg_replace('/' . $match . '/', file_get_contents($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $match . $ext), $content);                        
                    }
                    else
                    {

                        if(isset($this->excluded[$matches[2][$i]]))
                        {
                            $content = preg_replace('/' . $match . '/', $this->handler->render(new ControllerReference(preg_replace('/_/', ':', $matches[2][$i]), ['cache' => EntityMapper::excluded], []), 'inline'), $content);
                        }
                        else $this->cachedMap->addState($this->currentName, EntityMapper::rebuild);
                    }
                }
            }
            
            if($this->cachedMap->isState($this->currentName, EntityMapper::cached)) $event->setResponse(new Response($content));
        }


    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        $response = $event->getResponse();
        
        if(!in_array($response->getStatusCode(), [Response::HTTP_OK]))
        {
             return;
        }
        
        if($this->isAllowedToProccess($event))
        {
            $name =  $this->getName($event, '');
            if($this->cachedMap->isMapable($name))
            {
                if($this->cachedMap->isState($name, '')) $this->saveCacheFile($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $name, $response->getContent(), $response->headers->get('content-type'));
                $this->map[$name] = $response->getContent();    

                if(!$event->isMasterRequest()) $response->setContent($name);
                else $response->setContent(strtr($response->getContent(), $this->map));
            }


        }


    }

    private function saveCacheFile($path, $content, $contentType)
    {

        if(!file_exists(dirname($path))) mkdir(dirname($path), 0755, true);
        
        file_put_contents($path, $content);

        if($this->minifierCommand && $this->minifierCommandParams && strpos($contentType, 'text/html') !== false)
        {
            $process = new Process( strtr($this->minifierCommand . ' ' . $this->minifierCommandParams, [':target' => $path . '.min', ':source' => $path]) );
            $process->run();

            if ($this->kernel->getEnvironment() == 'dev' && !$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            else
            {
                unlink($path);
            }


            // if($is_master) $event->setResponse(new Response(file_get_contents($this->dir . $this->filename_min)));
        }
        else
        {
            rename($path, $path.'.min');
        }
    }

    private function getActionShort(Request $request)
    {
        return preg_replace('/[:]+/','_', strtr($request->attributes->get('_controller'), ['\\' => '', 'Controller' => ':', 'Action' => '']));
    }

    private function getActionWithParams(Request $request)
    {
        return $this->getActionShort($request) . '__' . sha1($request->getQueryString());
    }

    private function getName($event, $ext = '') 
    {
        return '___' . ($event->isMasterRequest() ? 'master' : 'fragment') . '__' . $this->getActionWithParams($event->getRequest())  . '___' . ($ext ? '.' . $ext : '');
    }


    public function generateEntityMapFilaname($entity, ClassMetadata $cm)
    {
        return '___map__' . sha1($cm->getName() . '_' . serialize($cm->getIdentifierValues($entity))) . '.json';
    }

    public function addEntityMap($entity, ClassMetadata $cm)
    {
        if($this->getCurrentName())
        {
            $this->cachedMap->createMapFile($this->cacheDir . CacheSubscriber::CACHE_MAPS_DIR, $this->generateEntityMapFilaname($entity, $cm), $this->getCurrentName());
        }
    }

    public function clearCacheByEntity($entity, ClassMetadata $cm)
    {
        $mapfile = $this->cacheDir . CacheSubscriber::CACHE_MAPS_DIR . $this->generateEntityMapFilaname($entity, $cm);
        if(file_exists($mapfile))
        {
            $map = json_decode(file_get_contents($mapfile), true);
            foreach($map as $file)
            {
                if(file_exists($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $file)) unlink($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $file);
                if(file_exists($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $file . '.min')) unlink($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $file. '.min');
            }
        }
    }

    public function callEntitiesFromQuery(\Doctrine\ORM\Query $query, \Doctrine\ORM\EntityManager $em)
    {
        if($this->getCurrentName())
        {
  
            $dql = $query->getDql();
       
            preg_match_all('/(FROM|JOIN) ([^ ]+) ([^ ]+)/', substr($dql, 0, strpos($dql, 'WHERE')), $matches);

            $select = '';
            foreach($matches[3] as $i => $alias)
            {
                $select .= ($select ? ', ' : '') . $alias; 

            }

            $q = $em->createQuery(preg_replace('/^SELECT .* FROM/', 'SELECT '.$select.' FROM', $dql));
            $q->setParameters($query->getParameters());
            $entities = $q->getResult();

            if($entities)
            {
                foreach($entities as $entity) $em->detach($entity);
            }

        }
    }


}