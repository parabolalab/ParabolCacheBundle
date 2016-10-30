<?php

namespace Parabol\CacheBundle\Event;

use Doctrine\Common\EventArgs;

class NotEntityEventArgs extends EventArgs
{

	private $query;

	private $em;

	public function __construct(\Doctrine\ORM\Query $q, \Doctrine\ORM\EntityManager $em)
	{
		$this->query = $q;
		$this->em = $em;
	}

	public function getQuery()
	{
		return $this->query;
	}

	public function getEntityManager()
	{
		return $this->em;
	}

}