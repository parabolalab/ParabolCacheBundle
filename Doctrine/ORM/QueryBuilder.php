<?php

namespace Parabol\CacheBundle\Doctrine\ORM;


class QueryBuilder extends \Doctrine\ORM\QueryBuilder
{
	public function getQuery()
	{
		$query = parent::getQuery();
		if($query instanceof \Parabol\CacheBundle\Doctrine\ORM\QueryContainer)
		{
			$trace = debug_backtrace();
			$caller = $trace[1];
			if(preg_match('#\\\ListController::getQuery$#',$caller['class'] . '::' . $caller['function']))
			{
				return $query->getContainedObject();
			}

		}
		return $query;
	}
}