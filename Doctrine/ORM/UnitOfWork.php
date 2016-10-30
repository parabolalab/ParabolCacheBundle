<?php 

namespace Parabol\CacheBundle\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Parabol\CacheBundle\Event\EntityEvents;


class UnitOfWork extends \Doctrine\ORM\UnitOfWork
{
    private $em;
    private $onReturnClassed = false;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        
        parent::__construct($em);
    }


    public function createEntity($className, array $data, &$hints = array())
    {
        $entity = parent::createEntity($className, $data, $hints);
        $this->em->getEventManager()->dispatchEvent(EntityEvents::onReturn, new \Doctrine\ORM\Event\LifecycleEventArgs($entity, $this->em));

        return $entity;
    }
}