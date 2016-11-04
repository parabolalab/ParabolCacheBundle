<?php

namespace Parabol\CacheBundle\Mapper;

class EntityMapper {

	const perform = 'perform'; 
	const excluded = 'excluded';
	const cached = 'cached';
	const rebuild = 'rebuild';

	private $states = [];

	public function addState($action, $state)
	{
		$this->states[$name] = $state;
	}
	
	public function isState($action, $state)
	{
		return isset($this->states[$name]) &&  $this->states[$name] == $state;
	}

	public function isMapable($action)
    {
        return isset($this->states[$name]) && ($this->states[$name] == '' || $this->states[$name] == self::excluded);
    }

    public function createMapFile($mapDir, $file, $action)
    {
        if(!file_exists($mapDir)) mkdir($mapDir, 0755, true);

        if(file_exists($mapDir . $file)) $mapData = json_decode(file_get_contents($mapDir . $file), true);
        else $mapData = [];

        if(!in_array($action, $mapData)) $mapData[] = $action;

        if(!empty($mapData))
        {
            if(!file_exists($mapDir)) mkdir($mapDir, 0755, true);
            file_put_contents($mapDir . $file, json_encode($mapData));
        }
    }

}