<?php

namespace Survos\ApiGrid\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Psr\Http\Client\ClientInterface;
use Survos\ApiGrid\Service\MeiliService;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\State\Pagination\Pagination;
use Meilisearch\Search\SearchResult;

class MeiliSearchStateProvider implements ProviderInterface
{
    public function __construct(
        private NormalizerInterface            $normalizer,
        private EntityManagerInterface         $em,
        private Pagination                     $pagination,
        private iterable                 $meiliSearchFilters,
        protected ClientInterface              $httpClient,
        protected MeiliService                 $meili,
        private string                         $meiliHost,
        private string                         $meiliKey,
        private readonly DenormalizerInterface $denormalizer
    )
    {
    }
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {

            $resourceClass = $operation->getClass();
            $body = [];

            foreach ($this->meiliSearchFilters as $meiliSearchFilter) {
                $body = $meiliSearchFilter->apply($body, $resourceClass, $operation, $context);
            }

            $searchQuery = isset($context['filters']['search'])?$context['filters']['search']:"";

            $body['limit'] = (int) $context['filters']['limit'] ??= $this->pagination->getLimit($operation, $context);
            $body['offset'] = (int) $context['filters']['offset'] ??= $this->pagination->getOffset($operation, $context);
            $body['attributesToHighlight'] = ['_translations'];
            $body['highlightPreTag'] = '<em class="bg-info">';
            $body['highlightPostTag'] =  '</em>';
//            dd($uriVariables, $context);
            $locale = $context['filters']['_locale'] ?? null;

            //
            if (!$indexName = isset($context['uri_variables']['indexName'])?$context['uri_variables']['indexName']:false) {
                $indexName = $this::getSearchIndexObject($operation->getClass(), $locale);
            }
            try {
                $index = $this->meili->getIndex($indexName);
                $data = $index->search($searchQuery, $body);
//                $client = $this->meili->getMeiliClient();
//                $index = $client->index($indexName);
            //dd($body);
            } catch (\Exception $exception) {
                dd($indexName, $searchQuery, $exception);
                throw new \Exception($indexName . ' ' . $exception->getMessage());
            }

            $data = $this->denormalizeObject($data, $resourceClass);
            unset($body['filter']);
            $body['limit'] = 0;
            $body['offset'] = 0;
            $facets = $index->search('', $body);

            return ['data' => $data, 'facets' => $facets];
        }

        return null;
    }

    public static function getSearchIndexObject(string $class, ?string $locale=null) {
        $class = explode("\\",$class);
        return end($class);
    }

    private function denormalizeObject(SearchResult $data, $resourceClass) {
        $returnObject['offset'] = $data->getOffset();
        $returnObject['limit'] = $data->getLimit();
        $returnObject['estimatedTotalHits'] = $data->getEstimatedTotalHits();
        $hits = $returnObject['hits'] = $this->denormalizer->normalize($data->getHits(), 'meili');
        $returnObject['processingTimeMs'] = $data->getProcessingTimeMs();
        $returnObject['query'] = $data->getQuery();
        $returnObject['facetDistribution'] = $data->getFacetDistribution();
        $returnObject['facetStats'] = $data->getFacetStats();
//        dd($returnObject['facetDistribution']['keywords'], $returnObject['facetStats']);
        return new SearchResult($returnObject);
    }
}
