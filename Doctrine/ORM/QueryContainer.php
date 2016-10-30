<?php

namespace Parabol\CacheBundle\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;

use Parabol\CacheBundle\Event\EntityEvents;

class QueryContainer
{
	private $query;

	public function __construct(EntityManagerInterface $em)
	{
		$this->query = new \Doctrine\ORM\Query($em);
	}

	public function __call($name, $args)
	{	
		$result = call_user_func_array([$this->query, $name], $args);
		if($result instanceof \Doctrine\ORM\Query) return $this;
		else return $result;
	}

	

    public function getContainedObject()
    {
    	return $this->query;
    }

    public function execute($parameters = null, $hydrationMode = null)
    { 
        $result = $this->query->execute($parameters, $hydrationMode);
        $this->callNotEntity();
        return $result;
    }

    public function getArrayResult()
    {
        $result = $this->query->getArrayResult();
        $this->callNotEntity();
        return $result;
    }

    public function getResult()
    {
        $result = $this->query->getResult();
        if(isset($result[0]) && is_array($result[0])) $this->callNotEntity();
        return $result;
    }
    public function getSingleResult()
    {
        $result = $this->query->getSingleResult();
        if(is_array($result)) $this->callNotEntity();
        return $result;
    }

    public function getSingleScalarResult()
    {
        $result = $this->query->getSingleScalarResult();
        $this->callNotEntity();
        return $result;
    }

    public function getScalarResult()
    {
        $result = $this->query->getScalarResult();
        $this->callNotEntity();
        return $result;
    }

    public function getOneOrNullResult()
    {
        $result = $this->query->getOneOrNullResult();
        if(is_array($result)) $this->callNotEntity();
        return $result;
    }

    private function callNotEntity()
    {
        $this->getEntityManager()->getEventManager()->dispatchEvent(EntityEvents::onNotEntity, new \Parabol\CacheBundle\Event\NotEntityEventArgs($this->query, $this->getEntityManager()));
    }

}