<?php

namespace Parabol\CacheBundle\EventListener;

use Doctrine\Common\EventSubscriber;

class CacheEntitySubscriber implements EventSubscriber 
{
    private $cacheSubscriber;

    public function __construct($cacheSubscriber)
	{
        $this->cacheSubscriber = $cacheSubscriber;
		
	}

	public function getSubscribedEvents()
    {
        return array(
           // 'postLoad' => 'postLoad', 
           'onReturn' => 'onReturn',
           'onNotEntity' => 'onNotEntity', 
           'postPersist' => 'postPersist',
           'postUpdate' => 'postUpdate',
           'postRemove' => 'postRemove',           
        );
    }

    public function postUpdate(\Doctrine\ORM\Event\LifecycleEventArgs $event)
    {
        $this->clearCache($event);
    }

    public function postPersist(\Doctrine\ORM\Event\LifecycleEventArgs $event)
    {
        $this->clearCache($event);
    }

    public function postRemove(\Doctrine\ORM\Event\LifecycleEventArgs $event)
    {
        $this->clearCache($event);
    }


    public function postLoad(\Doctrine\ORM\Event\LifecycleEventArgs $event)
    {   
        // var_dump('[EVENT ONLOAD] '  . get_class($event->getObject()) . ' ' . $event->getObject()->getId());
        // $this->cacheSubscriber->addEntityMap($event->getObject(), $this->getClassMetadata($event));
    }

    public function onReturn(\Doctrine\ORM\Event\LifecycleEventArgs $event)
    {   
        // var_dump('[EVENT ONRETURN] ' . get_class($event->getObject()) . ' ' . $event->getObject()->getId());
        $this->cacheSubscriber->addEntityMap($event->getObject(), $this->getClassMetadata($event));
    }

    private function getClassMetadata(\Doctrine\ORM\Event\LifecycleEventArgs $event)
    {
        return $event->getEntityManager()->getClassMetadata(get_class($event->getObject()));
    }

    private function clearCache(\Doctrine\ORM\Event\LifecycleEventArgs $event)
    {
        $this->cacheSubscriber->clearCacheByEntity($event->getObject(), $this->getClassMetadata($event));
    }

    public function onNotEntity(\Parabol\CacheBundle\Event\NotEntityEventArgs $event)
    {
        $this->cacheSubscriber->callEntitiesFromQuery($event->getQuery(), $event->getEntityManager());
    }



}