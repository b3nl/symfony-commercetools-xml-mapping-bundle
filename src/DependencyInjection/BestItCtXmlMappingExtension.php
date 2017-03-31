<?php

namespace BestIt\CtXmlMappingBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads the config for the broemmelhaupt customer import.
 * @author Bjoern Lange <lange@bestit-online.de>
 * @package BestIt\CtXmlMappingBundle
 * @subpackage DependencyInjection
 * @version $id$
 */
class BestItCtXmlMappingExtension extends Extension
{
    /**
     * Loads the bundle config.
     * @param array $configs
     * @param ContainerBuilder $container
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('best_it_ct_xml_mapping.mappings', $config['mappings'] ?? []);

        if (array_key_exists('logger', $config)) {
            $container->setAlias('best_it_ct_xml_mapping.logger', $config['logger']);
        }
    }
}
