<?php

namespace Parabol\CacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('parabol_cache');

        $rootNode
            ->children()
                ->booleanNode('cache_dev')->defaultValue(false)->end()
                ->scalarNode('minifier_command')->defaultValue('')->end()
                ->scalarNode('minifier_command_params')->defaultValue('-o :target :source --case-sensitive --collapse-boolean-attributes  --collapse-inline-tag-whitespace --collapse-whitespace --html5 --keep-closing-slash --remove-attribute-quotes --remove-comments --remove-empty-attributes --use-short-doctype --minify-css --minify-js')->end()
                ->arrayNode('exclude')
                    ->prototype('scalar')->end()
                ->end() 
                // ->arrayNode('dashboard')
                // ->addDefaultsIfNotSet()
                //     ->children()
                //         ->scalarNode('redirected')->defaultValue(false)->end()
                //         ->scalarNode('disabled')->defaultValue(false)->end()
                //     ->end() 
                // ->end()                
            ->end();

        return $treeBuilder;
    }
}
