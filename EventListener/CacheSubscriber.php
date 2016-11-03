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


class CacheSubscriber implements EventSubscriberInterface 
{
	const SUBCACHE_DIR = '/parabol/cachebundle/';
    const MAP_ENTITY_NAME = 'map_entity.yml';
    const CACHE_VIEWS_DIR = 'views/';
    const CACHE_MAPS_DIR = 'maps/';

    private $kernel;
    private $currentName = null;
    private $handler;
	private $excluded;
    private $excludedPattern;
	private $rebuildMap = [];
    private $cachedMap = [];
    private $map = [];
    
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

    // public function onKernelController(FilterControllerEvent $event)
    // {
    //     if ($this->kernel->getEnvironment() == 'dev')
    //     {
    //         return;
    //     }
        
    //     
    // }

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
            $this->cachedMap[$this->currentName] = '';
        }
        else 
        {
            return;
        }

        $ext = ($this->minifierCommand && $this->minifierCommandParams ? '.min' : '');

        if($request->attributes->get('cache') == 'excluded')
        {
             $this->cachedMap[$this->currentName] = 'perform';
        }
        elseif(isset($this->excluded[$this->getActionShort($request)]))
        {
            $this->cachedMap[$this->currentName] = 'excluded';
        }
        elseif(file_exists($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $this->currentName . $ext))
        {
            $this->cachedMap[$this->currentName] = 'cached';
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

                            $content = preg_replace('/' . $match . '/', $this->handler->render(new ControllerReference(preg_replace('/_/', ':', $matches[2][$i]), array('cache' => 'excluded'), array('cache' => 'excluded2')), 'inline'), $content);                            
                        }
                        else $this->cachedMap[$this->currentName] = 'rebuild';
                    }
                }
            }
            
            if($this->cachedMap[$this->currentName] == 'cached') $event->setResponse(new Response($content));
        }

        // var_dump($this->cachedMap[$name]);

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
            if($this->isMapable($name))
            {
                if($this->cachedMap[$name] == '') $this->saveCacheFile($this->cacheDir . CacheSubscriber::CACHE_VIEWS_DIR . $name, $response->getContent(), $response->headers->get('content-type'));
                $this->map[$name] = $response->getContent();    

                if(!$event->isMasterRequest()) $response->setContent($name);
                else $response->setContent(strtr($response->getContent(), $this->map));
            }


        }


    }

    private function isMapable($name )
    {
        return isset($this->cachedMap[$name]) && ($this->cachedMap[$name] == '' || $this->cachedMap[$name] == 'excluded');
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

    // public function addEntityMapByQuery($query, $params)
    // {

    //     if($this->getCurrentName())
    //     {

    //         if(in_array($query, [
    //                 'SELECT p0_.id AS id_0, p1_.title AS title_1, p1_.slug AS slug_2 FROM parabol_page p0_ LEFT JOIN parabol_page_translation p1_ ON p0_.id = p1_.translatable_id WHERE p0_.in_menu = ? ORDER BY p0_.sort ASC',

    //             ])) return;


    //         if(preg_match_all('/([\w\d]+)\.([\w\d]+) = (\?|\d+)/', $query, $matches))
    //         {
    //             foreach($matches[1] as $i => $p)
    //             {
    //                 if($p)
    //                 {
    //                     if(!isset($paramsMap[$p]))  $paramsMap[$p] = [];  
    //                     $paramsMap[$p][$matches[2][$i]] = $params[$i];
    //                 } 
    //             }
    //         }
    //         // var_dump( preg_replace('/^.*FROM/', '',$query), $matches);
    //         echo $query . '<br /><br />';

    //         var_dump($params, $paramsMap);

    //         if(preg_match_all('/ FROM (\w+) (\w+)/', $query, $matches))   
    //         {
    //             echo 'FROM <br /><br />';
    //             var_dump($matches);
    //             foreach($matches[1] as $i => $entity)
    //             {
    //                 if(isset($paramsMap[$entity]))
    //                 {
    //                     $params = $paramsMap[$entity];
    //                 }
    //                 elseif(isset($paramsMap[$matches[2][$i]]))
    //                 {
    //                     $params = $paramsMap[$matches[2][$i]];
    //                 }
    //                 // var_dump($paramsMap, $matches);
    //                 // var_dump(isset($paramsMap[$matches[2][$i]]));
    //                 $file = '___map__' . $entity . '___' . implode('__' , array_keys($params)) . '___' . sha1(serialize($params)).'.json';

                    
    //                 // var_dump($params);
    //                 echo $file . '<br /><br />';
    //             //     $this->createMapFile($file);
    //             }

    //         }


    //         if(preg_match_all('/ JOIN (\w+) (\w*) {0,1}ON/', $query, $matches))   
    //         {
    //             echo 'JOIN <br /><br />';
    //             var_dump($matches);
    //             foreach($matches[1] as $i => $entity)
    //             {
    //                 $params = null;
    //                 if(isset($paramsMap[$entity]))
    //                 {
    //                     $params = $paramsMap[$entity];
    //                 }
    //                 elseif(isset($paramsMap[$matches[2][$i]]))
    //                 {
    //                     $params = $paramsMap[$matches[2][$i]];
    //                 }

    //                 if($params)
    //                 {
    //                     $file = '___map__' . $entity . '___' . implode('__' , array_keys($params)) . '___' . sha1(serialize($params)).'.json';
    //                     echo $file . '<br /><br />';
    //                 } 
    //                 // echo 'JOIN <br /><br />';
                    
                    
    //                 // $this->createMapFile($file);
    //             }

    //         }

 

            
    //     } 
    // }

    public function generateEntityMapFilaname($entity, ClassMetadata $cm)
    {
        return '___map__' . sha1($cm->getName() . '_' . serialize($cm->getIdentifierValues($entity))) . '.json';
    }

    public function addEntityMap($entity, ClassMetadata $cm)
    {
        if($this->getCurrentName())
        {
            $this->createMapFile($this->generateEntityMapFilaname($entity, $cm));
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

    private function createMapFile($file)
    {
        $mapDir = $this->cacheDir . CacheSubscriber::CACHE_MAPS_DIR;
        if(!file_exists($mapDir)) mkdir($mapDir, 0755, true);

        if(file_exists($mapDir . $file)) $mapData = json_decode(file_get_contents($mapDir . $file), true);
        else $mapData = [];

        if(!in_array($this->getCurrentName(), $mapData)) $mapData[] = $this->getCurrentName();

        if(!empty($mapData))
        {
            if(!file_exists($mapDir)) mkdir($mapDir, 0755, true);
            file_put_contents($mapDir . $file, json_encode($mapData));
        }
    }

    public function callEntitiesFromQuery(\Doctrine\ORM\Query $query, \Doctrine\ORM\EntityManager $em)
    {
        if($this->getCurrentName())
        {
           
           


            $dql = $query->getDql();
       
            preg_match_all('/(FROM|JOIN) ([^ ]+) ([^ ]+)/', substr($dql, 0, strpos($dql, 'WHERE')), $matches);

            // $assocMap = [];            
            $select = '';
            foreach($matches[3] as $i => $alias)
            {
                $select .= ($select ? ', ' : '') . $alias; 

                // if(!$i) $class = $matches[2][$i];
                // else
                // {
                //     list($parentAlias, $name) = explode('.', $matches[2][$i]);
                //     $class = $assocMap[$parentAlias]['associations'][$name];
                // }

                // $cm = $em->getClassMetadata($class);
                // $select .= ($select ? ', ' : '') . $alias; 
                // $assocMap[$alias] = ['identifires' => $alias . '.' . implode(',' . $alias . '.', $cm->getIdentifier()), 'associations' => []];
                // if(!empty($cm->getAssociationMappings()))
                // {
                    
                //     foreach ($cm->getAssociationMappings() as $name => $assoc) {
                //         $assocMap[$alias]['associations'][$name] = $assoc['targetEntity'];
                //     }   
                // }
            }
            // var_dump($assocMap , $select);
           

            // var_dump(substr($dql, 0, strpos($dql, 'WHERE')), $matches, preg_replace('/^SELECT .* FROM/', 'SELECT '.$select.' FROM', $dql));
            // die();

            $q = $em->createQuery(preg_replace('/^SELECT .* FROM/', 'SELECT '.$select.' FROM', $dql));
            // var_dump($this->query->getParameter('id'));
            $q->setParameters($query->getParameters());
            $entities = $q->getResult();

            if($entities)
            {
                foreach($entities as $entity) $em->detach($entity);
            }

            // preg_match('/FROM ([^ ]+) ([^ ]+)/', $this->query->getDql(), $matches);

            // $cm = $this->getEntityManager()->getClassMetadata($matches[1]);
            
            // $identifiers = $cm->getIdentifier();

            // foreach($result as $item)
            // {
            //     $dev = [];
            //     foreach ($identifiers as $ident) {
            //         if(isset($item[$ident])) $dev[] = $item[$ident];
            //     }
            //     // var_dump('array result');
            //     if(count($dev) == count($identifiers))
            //     {
            //         // var_dump('generate map');
            //     }
            //     else
            //     {
            //         // 
            //     }
                
            // }

            // $this->getEntityManager()->getRepository() $matches[1]
            //     # code...
            // }

            // preg_match_all('/('.$matches[2].'\.[^ ]+) [^ ]+ (.*)(AND|OR)/', 'SELECT i FROM AppBundle\Entity\Foo i LEFT JOIN AppBundle\Entity\Boo b ON b.id = i.boo_id WHERE i.id = :id AND i.test = :test OR i.dupa IN (1,2,3,4)', $matches);

            // var_dump($matches[1]);
        }
    }


}