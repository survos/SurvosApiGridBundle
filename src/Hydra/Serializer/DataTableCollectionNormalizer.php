<?php

namespace Survos\ApiGrid\Hydra\Serializer;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\JsonLd\ContextBuilder;
use ApiPlatform\JsonLd\Serializer\JsonLdContextTrait;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Serializer\AbstractCollectionNormalizer;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use MartinGeorgiev\Doctrine\ORM\Query\AST\Functions\Arr;

final class DataTableCollectionNormalizer extends AbstractCollectionNormalizer
{
    use JsonLdContextTrait;

    public const FORMAT = 'jsonld';
    public const IRI_ONLY = 'iri_only';
    private array $defaultContext = [
        self::IRI_ONLY => false,
    ];

    public function __construct(
        private $contextBuilder, 
        ResourceClassResolverInterface $resourceClassResolver, 
        private readonly IriConverterInterface $iriConverter, 
        private readonly ?ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory = null, 
        array $defaultContext = []
    )
    {
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);

        if ($this->resourceMetadataCollectionFactory) {
            trigger_deprecation('api-platform/core', '3.0', sprintf('Injecting "%s" within "%s" is not needed anymore and this dependency will be removed in 4.0.', ResourceMetadataCollectionFactoryInterface::class, self::class));
        }

        parent::__construct($resourceClassResolver, '');
    }

    /**
     * {@inheritdoc}
     *
     * @param iterable $object
     */
    public function normalize(mixed  $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if (!isset($context['resource_class']) || isset($context['api_sub_level'])) {
            return $this->normalizeRawCollection($object, $format, $context);
        }

        $facets = [];
        if(is_array($object) && isset($object['facetDistribution'])) {
            parse_str(parse_url($context['request_uri'], PHP_URL_QUERY), $params);
            if(isset($params['facets']) && is_array($facets = json_decode($params['facets'],true))) {
                $facets = $this->getFacetsData($object['facetDistribution'], $facets);
            }
        }

        if(is_array($object) && isset($object['hits'])) {
            $object = $object['hits'];
        }

        if ($object instanceof PaginatorInterface) {
            parse_str(parse_url($context['request_uri'], PHP_URL_QUERY), $params);
            $em = $object->getQuery()->getEntityManager();
            $metadata = $em->getClassMetadata($context['operation']->getClass());
            $repo = $em->getRepository($context['operation']->getClass());
            if(isset($params['facets']) && is_array($params['facets'])) {
                $doctrineFacets = [];
                foreach($params['facets'] as $key => $facet) {
                    $keyArray = array_keys($metadata->getReflectionProperties());
                    if(in_array($key, $keyArray)) {
                        $doctrineFacets[$key] = $repo->getCounts($key);
                    }                    
                }

                $facets = $this->getFacetsData($doctrineFacets,$params['facets']);
            }
        }

        $resourceClass = $this->resourceClassResolver->getResourceClass($object, $context['resource_class']);
        $context = $this->initContext($resourceClass, $context);
        $data = [];
        
        $paginationData = $this->getPaginationData($object, $context);

        if (($operation = $context['operation'] ?? null) && method_exists($operation, 'getItemUriTemplate')) {
            $context['item_uri_template'] = $operation->getItemUriTemplate();
        }

        // We need to keep this operation for serialization groups for later
        if (isset($context['operation'])) {
            $context['root_operation'] = $context['operation'];
        }

        if (isset($context['operation_name'])) {
            $context['root_operation_name'] = $context['operation_name'];
        }

        unset($context['operation']);
        unset($context['operation_type'], $context['operation_name']);

        $itemsData = $this->getItemsData($object, $format, $context);

        return array_merge_recursive($data, $paginationData, $itemsData, ['hydra:facets' => $facets]);
    }

    /**
     * Gets the pagination data.
     */
    protected function getPaginationData(iterable $object, array $context = []): array
    {
        $resourceClass = $this->resourceClassResolver->getResourceClass($object, $context['resource_class']);
        // This adds "jsonld_has_context" by reference, we moved the code to this class.
        // To follow a note I wrote in the ItemNormalizer, we need to change the JSON-LD context generation as it is more complicated then it should.
        $data = $this->addJsonLdContext($this->contextBuilder, $resourceClass, $context);
        $data['@id'] = $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, $context['operation'] ?? null, $context);
        $data['@type'] = 'hydra:Collection';

        if ($object instanceof PaginatorInterface) {
            $data['hydra:totalItems'] = $object->getTotalItems();
        }

        if (\is_array($object) || ($object instanceof \Countable && !$object instanceof PartialPaginatorInterface)) {
            $data['hydra:totalItems'] = \count($object);
        }

        return $data;
    }

    /**
     * Gets items data.
     */
    protected function getItemsData(iterable $object, string $format = null, array $context = []): array
    {
        $data = [];
        $data['hydra:member'] = [];

        $iriOnly = $context[self::IRI_ONLY] ?? $this->defaultContext[self::IRI_ONLY];

        if (($operation = $context['operation'] ?? null) && method_exists($operation, 'getItemUriTemplate')) {
            $context['item_uri_template'] = $operation->getItemUriTemplate();
        }

        // We need to keep this operation for serialization groups for later
        if (isset($context['operation'])) {
            $context['root_operation'] = $context['operation'];
        }

        if (isset($context['operation_name'])) {
            $context['root_operation_name'] = $context['operation_name'];
        }

        // We need to unset the operation to ensure a proper IRI generation inside items
        unset($context['operation']);
        unset($context['operation_name'], $context['uri_variables']);

        foreach ($object as $obj) {
            if ($iriOnly) {
                $data['hydra:member'][] = $this->iriConverter->getIriFromResource($obj);
            } else {
                $data['hydra:member'][] = $this->normalizer->normalize($obj, $format, $context + ['jsonld_has_context' => true]);
            }
        }

        return $data;
    }

    protected function initContext(string $resourceClass, array $context): array
    {
        $context = parent::initContext($resourceClass, $context);
        $context['api_collection_sub_level'] = true;

        return $context;
    }

    private function getFacetsData(array $facets, ?array $params) :array {
        $facetsData = [];

        foreach($facets as $key => $facet) {
            $data = [];
            foreach($facet as $facetKey => $facetValue) {
                $fdata["label"] =  $facetKey;
                $fdata["total"] =  $facetValue;
                $fdata["value"] =  $facetKey;
                $fdata["count"] =  $facetValue;
                if(is_array($params[$key])) {
                    foreach($params[$key] as $param) {
                        if(isset($param['label']) && $param['label'] === $facetKey) {
                            $fdata['total'] = $param['total'];
                            break;
                        }
                    }
                }

                $data[] = $fdata;
            }
            $facetsData[$key] = $data;
        }

        foreach ($params as $key => $subArray) {
            if(is_array($subArray)) {
                foreach ($subArray as $bItem) {
                    $label = $bItem['label'];
                    if (!in_array($label, array_column($facetsData[$key], 'label'))) {
                        $facetsData[$key][] = [
                            'label' => $label,
                            'total' => $bItem['total'],
                            'value' => $label,
                            'count' => 0
                        ];
                    }
                }
            }
        }

        $returnData['searchPanes']['options'] = $facetsData;
        $returnData['searchPanes']["showZeroCounts"] = true;
        return $returnData;
    }

    private function createAllSearchPanesRecords(array $params) {
        $data = [];
        foreach ($params as $param) {
            $data[] = $this->createSearchPanesArray($param["label"] , 0);
        }
        return $data;
    }

    private function createSearchPanesArray($key,$count) {
        return ["label" => $key, "total" => $count , "value" => $key,  "count" => $count];
    }

}