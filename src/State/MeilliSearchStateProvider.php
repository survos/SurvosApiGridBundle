<?php

namespace Survos\ApiGrid\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Metadata\CollectionOperationInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Meilisearch\Bundle\SearchService;
use Meilisearch\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Tagged;
use ApiPlatform\Util\Inflector;
use ApiPlatform\State\Pagination\Pagination;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;

class MeilliSearchStateProvider implements ProviderInterface
{
    public function __construct(
        private NormalizerInterface $normalizer,
        private EntityManagerInterface $em,
        private Pagination $pagination,
        private iterable $meilliSearchFilter,
        private string $meiliHost,
        private string $meiliKey
    )
    {
    }
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {

            $resourceClass = $operation->getClass();
            $body = [];

            foreach ($this->meilliSearchFilter as $meilliSearchFilter) {
                $body = $meilliSearchFilter->apply($body, $resourceClass, $operation, $context);
            }

            $searchQuery = isset($context['filters']['search'])?$context['filters']['search']:"";

            $body['limit'] = (int) $context['filters']['limit'] ??= $this->pagination->getLimit($operation, $context);
            $body['offset'] = (int) $context['filters']['offset'] ??= $this->pagination->getOffset($operation, $context);

            $data = $this->getSearchIndexObject($operation->getClass())->search($searchQuery, $body);
            unset($body['filter']);
            $body['limit'] = 0;
            $body['offset'] = 0;
            $facets = $this->getSearchIndexObject($operation->getClass())->search('', $body);

            return ['data' => $data, 'facets' => $facets];
        }

        return null;
    }

    private function getSearchIndexObject(string $class) {
        $client = new Client($this->meiliHost, $this->meiliKey);
        $class = explode("\\",$class);
        $lastKey = strtolower(end($class));
        return $client->index($lastKey);
    }
}
