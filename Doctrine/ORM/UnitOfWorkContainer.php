<?php 

namespace Parabol\CacheBundle\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Parabol\CacheBundle\Event\EntityEvents;


class UnitOfWorkContainer
{
    private $unitOfWork;
    private $em;

    public function __construct($unitOfWork, EntityManagerInterface $em)
    {
        // var_dump(get_class($em));
        $this->unitOfWork = $unitOfWork;
        $this->em = $em;
        // parent::__construct($em);
    }

    public function __call($name, $args)
    {   
        if(method_exists($this, $name)) die('aaa');
        $result = call_user_func_array([$this->unitOfWork, $name], $args);
        return $result;
    }

    public function getContainedObject()
    {
        return $this->unitOfWork;
    }

	public function createEntity($className, array $data, &$hints = array())
    {
        var_dump('!!!!!createEntity');
        $entity = $this->unitOfWork->createEntity($className, $data, $hints);
        
        $this->em->getEventManager()->dispatchEvent(EntityEvents::onReturn, new \Doctrine\ORM\Event\LifecycleEventArgs($entity, $this->em));

        return $entity;
    }
}