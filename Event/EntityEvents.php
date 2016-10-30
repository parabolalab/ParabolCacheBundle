<?php

namespace Parabol\CacheBundle\Event;

class EntityEvents
{
    const onReturn = 'onReturn';

    const onNotEntity = 'onNotEntity';

    // private $_evm;

    // public $onReturnInvoked = false;

    // public function __construct($evm)
    // {
    //     $evm->addEventListener([self::onReturn], $this);
    // }

    // public function onReturn(\Doctrine\ORM\Event\LifecycleEventArgs $args)
    // {
    // 	var_dump('aaaa');
    //     $this->onReturnInvoked = true;
    // }
}