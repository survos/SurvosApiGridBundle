<?php

namespace Survos\ApiGrid\Api\Filter;

use ApiPlatform\Api\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use ApiPlatform\Exception\InvalidArgumentException;

/**
 * Selects entities where each search term is found somewhere
 * in at least one of the specified properties.
 * Search terms must be separated by spaces.
 * Search is case-insensitive.
 * All specified properties type must be string.
 * @package App\Filter
 */
class FacetsFieldSearchFilter extends AbstractFilter implements FilterInterface
{
    /**
     * Add configuration parameter
     * {@inheritdoc}
     * @param string $searchParameterName The parameter whose value this filter searches for
     */
    public function __construct(ManagerRegistry        $managerRegistry,
        LoggerInterface $logger = null,
        array $properties = null,
        NameConverterInterface $nameConverter = null,
        private string         $searchParameterName = 'facet_filter')
    {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }


    /** {@inheritdoc} */
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        if (null === $value || $property !== $this->searchParameterName) {
            return;
        }

        foreach ($value as $filter) {
            $words = explode(',', $filter);
            if(count($words) < 3) {
                return;
            }
            $key = $words[0];
            $values = $words[2]??'';

            if (strlen($values)) {
                $filterValue = explode('|', $words[2]);
            } else {
                $filterValue[0] = null;
            }
            $this->addWhereIn($queryBuilder, $filterValue, $key);
        }
        return;
    }

    private function addWhereIn(QueryBuilder $queryBuilder, array $word, string $parameterName) {
        $alias = $queryBuilder->getRootAliases()[0];
        if (count($word) && ($word[0] === null)) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->isNull(sprintf('%s.%s', $alias, $parameterName)));
        } else {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->in(sprintf('%s.%s', $alias, $parameterName),$word));
        }
    }

    public function getDescription(string $resourceClass): array
    {
        //        assert(false, $resourceClass);
        $props = $this->getProperties();
        if (null === $props) {
            throw new \InvalidArgumentException('Properties must be specified');
        }
        return [
            $this->searchParameterName => [
                'property' => implode(', ', array_keys($props)),
                'type' => 'string',
                'is_collection' => true,
                'required' => false,
                'swagger' => [
                    'description' => 'Selects entities where each search term is found somewhere in at least one of the specified properties',
                ],
            ],
        ];
    }
}
