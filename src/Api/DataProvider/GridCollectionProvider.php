<?php
// api/src/DataProvider/BlogPostCollectionDataProvider.php

namespace Survos\ApiGrid\Api\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\CollectionDataProvider;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Exception\RuntimeException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class GridCollectionProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{

    public function __construct(private CollectionDataProviderInterface $collectionDataProvider,
                                private ManagerRegistry $managerRegistry,
                                private iterable $collectionExtensions = [],
    )
    {
//        dd($this->collectionDataProvider);
    }

    /**
     * @param QueryCollectionExtensionInterface[] $collectionExtensions
     */
//    public function __construct(private ManagerRegistry $managerRegistry, private iterable $collectionExtensions = []) {
//    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return false;
        return $this->managerRegistry->getManagerForClass($resourceClass) instanceof EntityManagerInterface;
    }


    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException
     */
    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): iterable
    {

        return $this->collectionDataProvider->getCollection($resourceClass, $operationName, $context);


//        dd($resourceClass, $operationName, $context);
        /** @var EntityManagerInterface $manager */
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);

        $repository = $manager->getRepository($resourceClass);
        if (!method_exists($repository, 'createQueryBuilder')) {
            throw new RuntimeException('The repository class must have a "createQueryBuilder" method.');
        }

        $queryBuilder = $repository->createQueryBuilder('o');
        $queryNameGenerator = new QueryNameGenerator();
        foreach ($this->collectionExtensions as $extension) {
            dd($extension);
            $extension->applyToCollection($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($resourceClass, $operationName, $context)) {
                return $extension->getResult($queryBuilder, $resourceClass, $operationName, $context);
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
