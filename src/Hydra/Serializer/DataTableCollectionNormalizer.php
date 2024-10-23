<?php

namespace Survos\ApiGrid\Hydra\Serializer;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\JsonLd\AnonymousContextBuilderInterface;
use ApiPlatform\JsonLd\ContextBuilder;
use ApiPlatform\JsonLd\ContextBuilderInterface;
use ApiPlatform\Metadata\Error;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Serializer\AbstractCollectionNormalizer;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use ApiPlatform\Metadata\Util\IriHelper;
use Meilisearch\Search\SearchResult;
use Psr\Log\LoggerInterface;
use Survos\ApiGrid\Event\FacetEvent;
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Languages;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DataTableCollectionNormalizer extends AbstractCollectionNormalizer
{
    public const FORMAT = 'jsonld';

    public const FACETFORMAT = 'facet_format';
    public const IRI_ONLY = 'iri_only';
    private array $defaultContext = [
        self::IRI_ONLY => false,
    ];

    public function __construct(
        private             ContextBuilderInterface  $contextBuilder,
        ResourceClassResolverInterface                        $resourceClassResolver,
        private readonly LoggerInterface                      $logger,
        private EventDispatcherInterface                      $eventDispatcher,
        private readonly RequestStack                         $requestStack, // hack to add locafle
        private readonly IriConverterInterface                $iriConverter,
        protected ?ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        array                                                 $defaultContext = [],
        protected string                                      $pageParameterName = 'page'
    )
    {
//        dd($this->contextBuilder::class);
        $this->defaultContext = array_merge($this->defaultContext, $defaultContext);

        parent::__construct($resourceClassResolver, $pageParameterName);
    }

    /**
     * {@inheritdoc}
     *
     * @param iterable $object
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
//        dd($object, $format, $context);
//        $this->logger->error(sprintf("%s ", $format));
        if (!isset($context['resource_class']) || isset($context['api_sub_level'])) {
            return $this->normalizeRawCollection($object, $format, $context);
        }

        $paginationData = $this->getPaginationData($object, $context);
        $facets = [];
        $data = [];

        if (is_array($object) && isset($object['data']) && $object['data'] instanceof SearchResult) {
            parse_str(parse_url($context['request_uri'], PHP_URL_QUERY), $params);
            $locale = $params['_locale'] ?? null;
            $context['locale'] = $locale;
            if (isset($params['facets']) && is_array($params['facets'])) {
                $context['pixieCode'] = $params['pixieCode'] ?? false;

                $facets = $this->getFacetsData($object['data']->getFacetDistribution(),
                    $object['facets']->getFacetDistribution(), $context);
            }
            $data = $this->getNextData($object['data'], $context, []);
            $object = $object['data']->getHits();
        }

        if ($object instanceof PaginatorInterface) {
            $data = $this->getNextData($object, $context, []);
            if ($context['request_uri']) {
                parse_str(parse_url($context['request_uri'], PHP_URL_QUERY), $params);
                $em = $object->getQuery()->getEntityManager();
                $metadata = $em->getClassMetadata($context['operation']->getClass());
                $repo = $em->getRepository($context['operation']->getClass());
//                assert(is_subclass_of($repo, QueryBuilderHelperInterface::class),
//                    $repo::class . " must implement QueryBuilderHelperInterface");

                if (isset($params['facets']) && is_array($params['facets'])) {
                    $doctrineFacets = [];

                    foreach ($params['facets'] as $key => $facet) {
                        $keyArray = array_keys($metadata->getReflectionProperties());
                        if (in_array($facet, $keyArray)) {
                            try {
                                $counts = $repo->getCounts($facet);
                                $doctrineFacets[$facet] = $counts;
                            } catch (\Exception $exception) {
                                // @todo: handle arrays in doctrine, etc.

                            }
                        }
                    }

                    $facets = $this->getFacetsData($doctrineFacets, $doctrineFacets, $context);
                }
            }
        }

        $resourceClass = $this->resourceClassResolver->getResourceClass($object, $context['resource_class']);
        $context = $this->initContext($resourceClass, $context);

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

        if (is_array($object) && isset($object['data']) && $object['data'] instanceof SearchResult) {
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
        $this->logger->info(sprintf('%s %s for %s %s', __CLASS__, $context['root_operation_name'], $context['resource_class'], __METHOD__));

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
                $normalizedData = $this->iriConverter->getIriFromResource($obj);
//                $normalizedData =  $this->iriConverter->getIriFromResource($obj); // ??
                $normalizedData = $this->iriConverter->getResourceFromIri($obj);
            } else {
                $normalizedData = $this->normalizer->normalize($obj, $format, $context + ['jsonld_has_context' => true]);
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

    private function getFacetsData(array $facets, ?array $params, ?array $context): array
    {
        $facetsData = [];
        $locale = $context['locale'] ?? null;
        // a mess, needs refactoring
        if ($pixieCode = $context['uri_variables']['pixieCode'] ?? false) {
            $event = $this->eventDispatcher->dispatch(new FacetEvent($params,
                targetLocale: $locale,
                context: $context));
            $translatedFacets = $event->getFacets();
            foreach ($translatedFacets as $key => $facet) {
                $data = [];

                foreach ($facet as $facetKey => $fdata) {
//                dd($facet, $facetKey, $facetCount, $key);
                    // translation? js for language and country?
//                $fdata["label"] = $label;
//                $fdata["total"] = $facetValue;
                    $fdata["value"] = $facetKey;
//                $fdata["count"] = 0;
//                if (isset($facets[$key][$facetKey])) {
//                    $fdata["count"] = $facets[$key][$facetKey];
//                }
//                dd($fdata);
                    $data[] = $fdata;
                }
                $facetsData[$key] = $data;
            }
        } else {

            $facetsData = [];
            foreach ($params as $key => $facet) {
                $data = [];
                foreach ($facet as $facetKey => $facetValue) {
                    $fdata["label"] = $facetKey;
                    $fdata["total"] = $facetValue;
                    $fdata["value"] = $facetKey;
                    $fdata["count"] = 0;
                    if (isset($facets[$key][$facetKey])) {
                        $fdata["count"] = $facets[$key][$facetKey];
                    }
                    $data[] = $fdata;
                }
                $facetsData[$key] = $data;
            }

            $returnData['searchPanes']['options'] = $this->normalizer->normalize($facetsData, self::FACETFORMAT, $context);
            return $returnData;
        }

        $returnData['searchPanes']['options'] = $this->normalizer->normalize($facetsData, self::FACETFORMAT, $context);

        return $returnData;
    }

    private function createAllSearchPanesRecords(array $params)
    {
        $data = [];
        foreach ($params as $param) {
            $data[] = $this->createSearchPanesArray($param["label"], 0);
        }
        return $data;
    }

    private function createSearchPanesArray($key, $count)
    {
        return ["label" => $key, "total" => $count, "value" => $key, "count" => $count];
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

        if (($operation = $context['operation'] ?? null) && ($operation->getExtraProperties()['rfc_7807_compliant_errors'] ?? false) && $operation instanceof Error) {
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

    public function getSupportedTypes(?string $format): array
    {
        /*
         * At this point, support anything that is_iterable(), i.e. array|Traversable
         * for non-objects, symfony uses 'native-'.\gettype($data) :
         * https://github.com/tucksaun/symfony/blob/400685a68b00b0932f8ef41096578872b643099c/src/Symfony/Component/Serializer/Serializer.php#L254
         */
        if (static::FORMAT === $format) {
            return [
                'native-array' => true,
                '\Traversable' => true,
            ];
        }

        return [];
    }

    private function getNextData($object, $context, $data)
    {
        $parsed = IriHelper::parseIri($context['request_uri'] ?? '/', $this->pageParameterName);
        $currentPage = $lastPage = $itemsPerPage = $pageTotalItems = null;
        if ($paginated = ($object instanceof PartialPaginatorInterface)) {
            if ($object instanceof PaginatorInterface) {
                $paginated = 1. !== $lastPage = $object->getLastPage();
            } else {
                $itemsPerPage = $object->getItemsPerPage();
                $pageTotalItems = (float)\count($object);
            }

            $currentPage = $object->getCurrentPage();
        }

        if ($object instanceof SearchResult && $paginated = ($object instanceof SearchResult)) {
            $itemsPerPage = $object->getLimit();
            $lastPage = ceil($object->getEstimatedTotalHits() / $itemsPerPage);
            $pageTotalItems = $object->getEstimatedTotalHits();
            $currentPage = floor($object->getOffset() / $itemsPerPage) + 1;
        }

        $data['hydra:view'] = ['@id' => null, '@type' => 'hydra:PartialCollectionView'];

        $data['hydra:view']['@id'] = IriHelper::createIri($parsed['parts'], $parsed['parameters'], $this->pageParameterName, $paginated ? $currentPage : null);

        if ($paginated) {
            return $this->populateDataWithPagination($data, $parsed, $currentPage, $lastPage, $itemsPerPage, $pageTotalItems);
        }

        return $data;
    }


    private function populateDataWithPagination(array $data, array $parsed, ?float $currentPage, ?float $lastPage, ?float $itemsPerPage, ?float $pageTotalItems): array
    {
        if (null !== $lastPage) {
            $data['hydra:view']['hydra:first'] = IriHelper::createIri($parsed['parts'], $parsed['parameters'], $this->pageParameterName, 1.);
            $data['hydra:view']['hydra:last'] = IriHelper::createIri($parsed['parts'], $parsed['parameters'], $this->pageParameterName, $lastPage);
        }

        if (1. !== $currentPage) {
            $data['hydra:view']['hydra:previous'] = IriHelper::createIri($parsed['parts'], $parsed['parameters'], $this->pageParameterName, $currentPage - 1.);
        }

        if ((null !== $lastPage && $currentPage < $lastPage) || (null === $lastPage && $pageTotalItems >= $itemsPerPage)) {
            $data['hydra:view']['hydra:next'] = IriHelper::createIri($parsed['parts'], $parsed['parameters'], $this->pageParameterName, $currentPage + 1.);
        }

        return $data;
    }
}
