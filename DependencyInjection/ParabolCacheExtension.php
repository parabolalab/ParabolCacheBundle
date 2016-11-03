<?php

namespace Parabol\CacheBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class ParabolCacheExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('parabol_cache.cache_dev', $config['cache_dev']);   
        $container->setParameter('parabol_cache.minifier_command', $config['minifier_command']);   
        $container->setParameter('parabol_cache.minifier_command_params', $config['minifier_command_params']);
        $container->setParameter('parabol_cache.exclude_pattern', $config['exclude_pattern']);    

        $exclude = [];

        foreach($config['exclude'] as $i => $action) 
        {
            $exclude[preg_replace('/[:]+/','_', strtr($action, ['\\' => '', 'Controller' => ':', 'Action' => '']))] = 'all';
        }

        $container->setParameter('parabol_cache.exclude', $exclude);    

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
