<?php

namespace Survos\ApiGrid\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Doctrine\DBAL\Exception;
use Psr\Http\Client\ClientInterface;
use Survos\ApiGrid\Service\MeiliService;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\State\Pagination\Pagination;
use Meilisearch\Search\SearchResult;
use Symfony\Component\Stopwatch\Stopwatch;

class MeiliSearchStateProvider implements ProviderInterface
{
    public function __construct(
        private NormalizerInterface            $normalizer,
        private EntityManagerInterface         $em,
        private Pagination                     $pagination,
        private iterable                 $meiliSearchFilters,
        protected MeiliService                 $meili,
        private string                         $meiliHost,
        private string                         $meiliKey,
        private readonly DenormalizerInterface $denormalizer,
        protected ?ClientInterface              $httpClient=null,
        private ?Stopwatch $stopwatch = null
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

            $searchQuery = $context['filters']['search']??'';

            $body['limit'] = (int) $context['filters']['limit'] ??= $this->pagination->getLimit($operation, $context);
            $body['offset'] = (int) $context['filters']['offset'] ??= $this->pagination->getOffset($operation, $context);
            $body['attributesToHighlight'] = ['_translations'];
            $body['highlightPreTag'] = '<em class="bg-info">';
            $body['highlightPostTag'] =  '</em>';
            $body['showRankingScore'] = true;
            $locale = $context['filters']['_locale'] ?? null;

            //
            if (!$indexName = $context['uri_variables']['indexName'] ?? false) {
                $indexName = $this::getSearchIndexObject($operation->getClass(), $locale);
            }
                $index = $this->meili->getIndex($indexName);
            $event = $this->stopwatch->start('meili-search', 'meili');
                $data = $index->search($searchQuery, $body);
                $event->stop();

//            dd($context, $indexName);
            try {
//                $client = $this->meili->getMeiliClient();
//                $index = $client->index($indexName);
            //dd($body);
            } catch (\Exception $exception) {
                throw new \Exception($indexName . ' -- ' . $exception->getMessage());
            }

            $event = $this->stopwatch->start('meili-denormalizeObject', 'meili');
            $data = $this->denormalizeObject($data, $resourceClass);
            $event->stop();
            unset($body['filter']);
            $body['limit'] = 0;
            $body['offset'] = 0;

            $event = $this->stopwatch->start('facets', 'meili');
            $facets = $index->search('', $body);
            $event->stop();

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


        $returnObject['hits'] = $this->denormalizer->normalize($data->getHits(), 'meili');
        $returnObject['processingTimeMs'] = $data->getProcessingTimeMs();
        $returnObject['query'] = $data->getQuery();
        $returnObject['facetDistribution'] = $data->getFacetDistribution();
        $returnObject['facetStats'] = $data->getFacetStats();
//        dd($returnObject['facetDistribution']['keywords'], $returnObject['facetStats']);
        return new SearchResult($returnObject);
    }
}
