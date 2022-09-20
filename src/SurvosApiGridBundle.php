<?php

namespace Survos\ApiGrid;

use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Survos\ApiGrid\Components\ApiGridComponent;
use Survos\ApiGrid\Twig\TwigExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\WebpackEncoreBundle\Twig\StimulusTwigExtension;
use Twig\Environment;

class SurvosApiGridBundle extends AbstractBundle
{
    // $config is the bundle Configuration that you usually process in ExtensionInterface::load() but already merged and processed
    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        //        $builder
        //            ->setDefinition('survos.inspection_bundle', new Definition(\Survos\InspectionBundle\Twig\TwigExtension::class))
        //            ->setArgument('$iriConverter', new Reference('api_platform.iri_converter'))
        //            ->addTag('twig.extension')
        //            ->setPublic(false)
        //        ;

        if (class_exists(Environment::class) && class_exists(StimulusTwigExtension::class)) {
            $builder
                ->setDefinition('survos.api_grid_bundle', new Definition(TwigExtension::class))
                ->addTag('twig.extension')
                ->setPublic(false)
            ;
        }

        $builder->register(ApiGridComponent::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$twig', new Reference('twig'))
            ->setArgument('$logger', new Reference('logger'))
            ->setArgument('$stimulusController', $config['stimulus_controller'])
        ;
        $builder->register(MultiFieldSearchFilter::class)
            ->addArgument(new Reference('doctrine.orm.default_entity_manager'))
            ->addArgument(new Reference('request_stack'))
            ->addArgument(new Reference('logger'))
            ->addTag('api_platform.filter');

        //        $builder->register(GridComponent::class);
        //        $builder->autowire(GridComponent::class);

        //        $definition->setArgument('$widthFactor', $config['widthFactor']);
        //        $definition->setArgument('$height', $config['height']);
        //        $definition->setArgument('$foregroundColor', $config['foregroundColor']);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        // since the configuration is short, we can add it here
        $definition->rootNode()
            ->children()
            ->scalarNode('stimulus_controller')->defaultValue('@survos/api-grid-bundle/api_grid')->end()
            ->scalarNode('widthFactor')->defaultValue(2)->end()
            ->scalarNode('height')->defaultValue(30)->end()
            ->scalarNode('foregroundColor')->defaultValue('green')->end()
            ->end();

        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $configs = $builder->getExtensionConfig('api_platform');
        //        dd($configs);
        //        assert($configs[0]['defaults']['pagination_client_items_per_page'], "pagination_client_items_per_page must be tree in config/api_platform");

        // https://stackoverflow.com/questions/72507212/symfony-6-1-get-another-bundle-configuration-data/72664468#72664468
        //        // iterate in reverse to preserve the original order after prepending the config
        //        foreach (array_reverse($configs) as $config) {
        //            $container->prependExtensionConfig('my_maker', [
        //                'root_namespace' => $config['root_namespace'],
        //            ]);
        //        }
    }
}
