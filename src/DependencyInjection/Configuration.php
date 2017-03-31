<?php

namespace BestIt\CtXmlMappingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ParentNodeDefinitionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration class for this bundle.
 * @author Bjoern Lange <lange@bestit-online.de>
 * @package BestIt\CtXmlMappingBundle
 * @subpackage DependencyInjection
 * @version $id$
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Parses the config.
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $builder = new TreeBuilder();

        $rootNode = $builder->root('best_it_ct_xml_mapping');

        $rootNode
            ->children()
                ->scalarNode('logger')
                    ->info('Please provide the service id for your logging service.')
                ->end()
                ->append($this->getMappingNode())
            ->end()
        ;

        return $builder;
    }

    /**
     * Returns the mapping node.
     * @return ParentNodeDefinitionInterface
     */
    private function getMappingNode(): ParentNodeDefinitionInterface
    {
        $builder = new TreeBuilder();

        $node = $builder->root('mappings');

        $node
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('model')
            ->prototype('array')
                ->info('Please provider the full qualified class name for the model class you want to be mapped.')
                ->children()

                    ->arrayNode('fields')
                        ->info('Map your model fields to xml nodes.')
                        ->isRequired()
                        ->requiresAtLeastOneElement()
                        ->useAttributeAsKey('field')
                        ->prototype('array')
                            ->children()
                                ->variableNode('default')
                                    ->info('You can provide a default value instead of the nodes.')
                                    ->defaultValue('')
                                ->end()
                                ->arrayNode('nodes')
                                    ->info(
                                        'Provide your xpaths relative to the parent node to get the data from your ' .
                                        'nodes.'
                                    )
                                    ->prototype('scalar')->end()
                                ->end()
                                ->arrayNode('processor')
                                    ->info(
                                        'Please provide your PHP callbacks to post process to found data.'
                                    )
                                    ->prototype('variable')->end()
                                ->end()
                                ->scalarNode('separator')
                                    ->info(
                                        'Which scalar value (or toString-able object) should be used to concatenate ' .
                                        'multiple xml values.'
                                    )
                                ->end()

                                ->booleanNode('raw')
                                    ->info('Would you like to process the raw nodes?')
                                    ->defaultValue(false)
                                ->end()
                            ->end()
                        ->end()
                    ->end()

                ->end()
            ->end()
        ;

        return $node;
    }
}
