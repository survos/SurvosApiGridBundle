<?php

namespace Survos\ApiGrid\Hydra\Serializer;

//use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Symfony\Routing\IriConverter;
use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\JsonLd\AnonymousContextBuilderInterface;
use ApiPlatform\JsonLd\ContextBuilder;
use ApiPlatform\JsonLd\ContextBuilderInterface;
use ApiPlatform\JsonLd\Serializer\JsonLdContextTrait;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Serializer\AbstractCollectionNormalizer;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use MartinGeorgiev\Doctrine\ORM\Query\AST\Functions\Arr;
use Meilisearch\Search\SearchResult;
use Symfony\Component\HttpFoundation\RequestStack;

final class DataTableCollectionNormalizer extends AbstractCollectionNormalizer
{
    public const FORMAT = 'jsonld';

    public const FACETFORMAT = 'facet_format';
    public const IRI_ONLY = 'iri_only';
    private array $defaultContext = [
        self::IRI_ONLY => false,
    ];

    public function __construct(
        private $contextBuilder,
        ResourceClassResolverInterface $resourceClassResolver,
        private RequestStack $requestStack, // hack!

        private readonly IriConverter $iriConverter,
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

        $paginationData = $this->getPaginationData($object, $context);
        $facets = [];
        if(is_array($object) && isset($object['data']) && $object['data'] instanceof SearchResult) {
            parse_str(parse_url($context['request_uri'], PHP_URL_QUERY), $params);
            if(isset($params['facets']) && is_array($params['facets'])) {
                $facets = $this->getFacetsData($object['data']->getFacetDistribution(), $object['facets']->getFacetDistribution(), $context);
            }
            $object = $object['data']->getHits();
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

                $facets = $this->getFacetsData($doctrineFacets,$doctrineFacets, $context);
            }
        }

        $resourceClass = $this->resourceClassResolver->getResourceClass($object, $context['resource_class']);
        $context = $this->initContext($resourceClass, $context);
        $data = [];

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

        if(is_array($object) && isset($object['data']) && $object['data'] instanceof SearchResult) {
            $data['hydra:totalItems'] = $object['data']->getEstimatedTotalHits();
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
                $normalizedData =  $this->iriConverter->getIriFromResource($obj);
            } else {
                $normalizedData =  $this->normalizer->normalize($obj, $format, $context + ['jsonld_has_context' => true]);
            }
            // hack -- this should be its own normalizer.  Plus, this needs to be recursive
            if (array_key_exists('rp', $normalizedData)) {
                $request = $this->requestStack->getCurrentRequest();
                $normalizedData['rp']['_locale'] = $request->getLocale();

            }
            $data['hydra:member'][] = $normalizedData;
        }

        return $data;
    }

    protected function initContext(string $resourceClass, array $context): array
    {
        $context = parent::initContext($resourceClass, $context);
        $context['api_collection_sub_level'] = true;

        return $context;
    }

    private function getFacetsData(array $facets, ?array $params, ?array $context) :array {
        $facetsData = [];

        foreach($params as $key => $facet) {
            $data = [];
            foreach($facet as $facetKey => $facetValue) {
                $fdata["label"] =  $facetKey;
                $fdata["total"] =  $facetValue;
                $fdata["value"] =  $facetKey;
                $fdata["count"] =  0;
                if(isset($facets[$key][$facetKey])) {
                    $fdata["count"] = $facets[$key][$facetKey];
                }
                $data[] = $fdata;
            }
            $facetsData[$key] = $data;
        }

        $returnData['searchPanes']['options'] = $this->normalizer->normalize($facetsData, self::FACETFORMAT, $context);

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

    // copied from JsonLdContextTrait, which is internal
    private function addJsonLdContext(ContextBuilderInterface $contextBuilder, string $resourceClass, array &$context, array $data = []): array
    {
        if (isset($context['jsonld_has_context'])) {
            return $data;
        }

        $context['jsonld_has_context'] = true;

        if (isset($context['jsonld_embed_context'])) {
            $data['@context'] = $contextBuilder->getResourceContext($resourceClass);

            return $data;
        }

        $data['@context'] = $contextBuilder->getResourceContextUri($resourceClass);

        return $data;
    }

    private function createJsonLdContext(AnonymousContextBuilderInterface $contextBuilder, $object, array &$context): array
    {
        // We're in a collection, don't add the @context part
        if (isset($context['jsonld_has_context'])) {
            return $contextBuilder->getAnonymousResourceContext($object, ($context['output'] ?? []) + ['api_resource' => $context['api_resource'] ?? null, 'has_context' => true]);
        }

        $context['jsonld_has_context'] = true;

        return $contextBuilder->getAnonymousResourceContext($object, ($context['output'] ?? []) + ['api_resource' => $context['api_resource'] ?? null]);
    }

}
