<?php

namespace Survos\ApiGrid;

use Survos\ApiGrid\Api\Filter\FacetsFieldSearchFilter;
use Survos\ApiGrid\Api\Filter\MultiFieldSearchFilter;
use Survos\ApiGrid\Command\ApiIndexCommand;
use Survos\ApiGrid\Command\IndexCommand;
use Survos\ApiGrid\Components\GridComponent;
use Survos\ApiGrid\Components\ItemGridComponent;
use Survos\ApiGrid\Controller\GridController;
use Survos\ApiGrid\Controller\MeiliController;
use Survos\ApiGrid\Filter\MeiliSearch\MultiFieldSearchFilter as MeiliMultiFieldSearchFilter;
use Survos\ApiGrid\Components\ApiGridComponent;
use Survos\ApiGrid\Paginator\SlicePaginationExtension;
use Survos\ApiGrid\Service\DatatableService;
use Survos\ApiGrid\Service\MeiliService;
use Survos\ApiGrid\Twig\TwigExtension;
use Survos\CoreBundle\Traits\HasAssetMapperTrait;
use Survos\InspectionBundle\Services\InspectionService;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\UX\Chartjs\Builder\ChartBuilder;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Twig\Environment;
use Survos\ApiGrid\Filter\MeiliSearch\SortFilter;
use Survos\ApiGrid\Filter\MeiliSearch\DataTableFilter;
use Survos\ApiGrid\Filter\MeiliSearch\DataTableFacetsFilter;
use Survos\ApiGrid\State\MeiliSearchStateProvider;
use Survos\ApiGrid\Hydra\Serializer\DataTableCollectionNormalizer;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

class SurvosApiGridBundle extends AbstractBundle
{
    use HasAssetMapperTrait;
    // $config is the bundle Configuration that you usually process in ExtensionInterface::load() but already merged and processed
    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {

        $builder->register('survos_api_grid_datatable_service', DatatableService::class)
            ->setAutowired(true);

        $builder->register('api_meili_service', MeiliService::class)
            ->setArgument('$entityManager', new Reference('doctrine.orm.entity_manager'))
            ->setArgument('$config',$config)
            ->setArgument('$meiliHost',$config['meiliHost'])
            ->setArgument('$meiliKey',$config['meiliKey'])
            ->setArgument('$httpClient',new Reference('httplug.http_client'))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$bag', new Reference('parameter_bag'))
            ->setAutowired(true)
            ->setPublic(true)
            ->setAutoconfigured(true)
        ;

        // doctrine entities and inspection
        $builder->autowire(GridController::class)
            ->addTag('container.service_subscriber')
            ->addTag('controller.service_arguments')
            ->setArgument('$chartBuilder', new Reference('chartjs.builder', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setAutoconfigured(true)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        // meili index stats, etc.
        $builder->autowire(MeiliController::class)
            ->addTag('container.service_subscriber')
            ->addTag('controller.service_arguments')
            ->setArgument('$meili', new Reference('api_meili_service'))
            ->setArgument('$chartBuilder', new Reference('chartjs.builder', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setAutoconfigured(true)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        // check https://github.com/zenstruck/console-extra/issues/59
        $definition = $builder->autowire(IndexCommand::class)
            ->setArgument('$entityManager', new Reference('doctrine.orm.entity_manager'))
            ->setArgument('$bag', new Reference('parameter_bag'))
            ->setArgument('$serializer', new Reference('serializer'))
            ->setArgument('$meiliService', new Reference('api_meili_service'))
            ->setArgument('$datatableService', new Reference('survos_api_grid_datatable_service'))
//            ->setArgument('$normalizer', new Reference('normalizer'))
            ->addTag('console.command')
        ;
//        $definition->addMethodCall('setInvokeContainer', [new Reference('container')]);

        $definition = $builder->autowire(ApiIndexCommand::class)
            ->setAutoconfigured(true)
            ->setArgument('$entityManager', new Reference('doctrine.orm.entity_manager'))
            ->setArgument('$bag', new Reference('parameter_bag'))
            ->setArgument('$serializer', new Reference('serializer'))
//            ->setArgument('$normalizer', new Reference('normalizer'))
            ->addTag('console.command')
        ;


        if (class_exists(Environment::class)) {
            $builder
                ->setDefinition('survos.api_grid_bundle', new Definition(TwigExtension::class))
                ->addTag('twig.extension')
                ->setPublic(false);
        }

        $builder->register(GridComponent::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$twig', new Reference('twig'))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$stimulusController', $config['grid_stimulus_controller'])
            ->setArgument('$registry', new Reference('doctrine'))
        ;

        $builder->register(ItemGridComponent::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        $builder->register(DataTableFilter::class)
            ->setAutowired(true)
            ->addTag('meili_search_filter')
        ;
        $builder->register(MeiliMultiFieldSearchFilter::class)
            ->setAutowired(true)
            ->addTag('meili_search_filter')
        ;
        $builder->register(DataTableFacetsFilter::class)
            ->setAutowired(true)
            ->addTag('meili_search_filter')
        ;

        $builder->register(SortFilter::class)
            ->setAutowired(true)
            ->addTag('meili_search_filter')
        ;

        $builder->register(MeiliSearchStateProvider::class)
            ->setArgument('$meiliSearchFilters',tagged_iterator('meili_search_filter'))
            ->setArgument('$meili', new Reference('api_meili_service'))
            ->setArgument('$httpClient',new Reference('httplug.http_client'))
            ->setArgument('$meiliHost',$config['meiliHost'])
            ->setArgument('$meiliKey',$config['meiliKey'])
            ->setArgument('$denormalizer', new Reference('serializer'))
            ->addTag('api_platform.state_provider')
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $builder->register('api_platform.hydra.normalizer.collection', DataTableCollectionNormalizer::class)
//        $builder->register(DataTableCollectionNormalizer::class)
            ->setArgument('$contextBuilder', new Reference('api_platform.jsonld.context_builder'))
            ->setArgument('$resourceClassResolver', new Reference('api_platform.resource_class_resolver'))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$iriConverter', new Reference('api_platform.symfony.iri_converter'))
            ->setArgument('$requestStack', new Reference('request_stack'))
            ->addTag('serializer.normalizer', ['priority' => -985]);

//        $container->services()->alias(MeiliCollectionNormalizer::class,'api_platform.hydra.normalizer.collection');
        // $builder->register('api_platform.hydra.normalizer.partial_collection_view', PartialCollectionViewNormalizer::class)
        //     ->setArgument('$collectionNormalizer', new Reference('api_platform.hydra.normalizer.partial_collection_view.inner'))
        //     ->setArgument('$pageParameterName', new Reference('api_platform.collection.pagination.page_parameter_name'))
        //     ->setArgument('$enabledParameterName', new Reference('api_platform.collection.pagination.enabled_parameter_name'))
        //     ->setArgument('$resourceMetadataFactory', new Reference('api_platform.metadata.resource.metadata_collection_factory'))
        //     ->setArgument('$propertyAccessor', new Reference('api_platform.property_accessor'))
        //     ->setPublic(false)
        //     ->setDecoratedService(MeiliCollectionNormalizer::class);

        $builder->register('api_platform.doctrine.orm.query_extension.pagination',SlicePaginationExtension::class)
            ->setAutowired(true)
            ->addTag('api_platform.doctrine.orm.query_extension.collection', ['priority' => -60])
        ;
        $services = $container->services();
        $services->set(SlicePaginationExtension::class)
            ->tag('api_platform.doctrine.orm.query_extension.collection', ['priority' => -60])
        ;
        $container->services()->alias(SlicePaginationExtension::class,'api_platform.doctrine.orm.query_extension.pagination');

        $builder->register(DatatableService::class)->setAutowired(true)->setAutoconfigured(true);

        $builder->register(ApiGridComponent::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$twig', new Reference('twig'))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$datatableService', new Reference(DatatableService::class))
            ->setArgument('$inspectionService', new Reference(InspectionService::class))
            ->setArgument('$meiliService', new Reference('api_meili_service'))
            ->setArgument('$stimulusController', $config['stimulus_controller']);
        $builder->register(MultiFieldSearchFilter::class)
            ->addArgument(new Reference('doctrine.orm.default_entity_manager'))
            ->addArgument(new Reference('request_stack'))
            ->addArgument(new Reference('logger'))
            ->addTag('api_platform.filter');

        //        $builder->register(SimpleDatatablesComponent::class);
        //        $builder->autowire(SimpleDatatablesComponent::class);

        //        $definition->setArgument('$widthFactor', $config['widthFactor']);
        //        $definition->setArgument('$height', $config['height']);
        //        $definition->setArgument('$foregroundColor', $config['foregroundColor']);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        // since the configuration is short, we can add it here
        $definition->rootNode()
            ->children()
            ->scalarNode('stimulus_controller')
                ->info('The stimulus controller to use, should extend @survos/api-grid-bundle/api_grid')
                ->defaultValue('@survos/api-grid-bundle/api_grid')
            ->end()
            ->scalarNode('grid_stimulus_controller')->defaultValue('@survos/api-grid-bundle/grid')->end()
            ->scalarNode('meiliHost')->defaultValue('%env(MEILI_SERVER)%')->end()
            ->scalarNode('meiliKey')->defaultValue('%env(MEILI_API_KEY)%')->end()
            ->scalarNode('meiliPrefix')->defaultValue('%env(MEILI_PREFIX)%')->end()
            ->booleanNode('passLocale')->defaultValue(false)->end()
            ->integerNode('maxValuesPerFacet')
                ->info('https://specs.meilisearch.com/specifications/text/157-faceting-setting-api.html#_3-functional-specification')
                ->defaultValue(100)
            ->end()
            ->end();;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->isAssetMapperAvailable($builder)) {
            return;
        }

        $dir = realpath(__DIR__.'/../assets/');
        assert(file_exists($dir), $dir);

        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    $dir => '@survos/api-grid',
                ],
            ],
        ]);
    }
}
