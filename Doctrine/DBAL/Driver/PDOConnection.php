<?php

namespace Parabol\CacheBundle\Doctrine\DBAL\Driver;

use \Doctrine\DBAL\Cache\QueryCacheProfile;

class PDOConnection extends \Doctrine\DBAL\Connection {
	
	private $cacheSubscriber;

	public function executeQuery($query, array $params = array(), $types = array(), QueryCacheProfile $qcp = null)
	{
		// if($this->cacheSubscriber) $this->cacheSubscriber->addEntityMapByQuery($query, $params);
		// echo('<br />' . $query. '<br />');
		return parent::executeQuery($query, $params, $types, $qcp);
		
	}

	public function setCacheSubscriber($cacheSubscriber)
	{
		$this->cacheSubscriber = $cacheSubscriber;
	}
}