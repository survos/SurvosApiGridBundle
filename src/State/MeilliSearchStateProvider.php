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

            return $this->getSearchIndexObject($operation->getClass())->search($searchQuery, $body);
        }
        return null;
        // Retrieve the state from somewhere
        //return $this->em->getRepository($operation->getClass())->find($uriVariables['imdbId']);
    }

    private function getSearchIndexObject(string $class) {
        $client = new Client($this->meiliHost, $this->meiliKey);
        $class = explode("\\",$class);
        $lastKey = strtolower(end($class));
        return $client->index($lastKey);
    }

    private function returnObject(array $objectData, string $class) : object|array|null{
        $returnObject = [];
        foreach($objectData['hits'] as $hit) {
            $oject = new $class($hit);
            $methods = get_class_methods($oject);
            foreach ($methods as $method) {
                if (strpos($method, 'set') === 0) {
                    $variableName = strtolower(substr($method, 3));
                    $data = isset($hit[$variableName])?$hit[$variableName]:"";
                    if($variableName == 'imdbid' || $variableName == 'runtimeminutes') {
                        $data = (int) $data;
                    }
                    $oject->$method($data);
                }
            }
            $returnObject[] = $oject;
        }
        return $returnObject;
    }
}
