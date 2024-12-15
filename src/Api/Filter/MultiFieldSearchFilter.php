<?php

namespace Survos\ApiGrid\Api\Filter;

use ApiPlatform\Metadata\FilterInterface;
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
class MultiFieldSearchFilter extends AbstractFilter implements FilterInterface
{
    /**
     * Add configuration parameter
     * {@inheritdoc}
     * @param string $searchParameterName The parameter whose value this filter searches for
     */
    public function __construct(ManagerRegistry        $managerRegistry,
                                ?LoggerInterface $logger = null,
                                ?array $properties = null,
                                ?NameConverterInterface $nameConverter = null,
                                private string         $searchParameterName = 'search')
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
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (null === $value || $property !== $this->searchParameterName) {
            return;
        }

        $words = explode(' ', $value);
        foreach ($words as $word) {
            if (empty($word)) {
                continue;
            }
            $this->addWhere($queryBuilder, $word, $queryNameGenerator->generateParameterName($property));
        }
    }

    private function addWhere(QueryBuilder $queryBuilder, string $word, string $parameterName)
    {
        $alias = $queryBuilder->getRootAliases()[0];

        // for translatable fields, we need to join and search the translations, not the entity itself.
        // OR search the translated strings in the JSON
        // OR use ElasticSearch



        // Build OR expression
        $orExp = $queryBuilder->expr()->orX();
        foreach ($this->getProperties() as $prop => $ignoored) {
            $orExp->add($queryBuilder->expr()->like('LOWER(' . $alias . '.' . $prop . ')', ':' . $parameterName));
        }
//        dd($orExp->getParts(), $queryBuilder);

        // @todo: this is supposed to be looking for tsquery types!  hack!
        if ($prop === 'headlineText') {
            if (strlen($word) > 2) {
                $queryBuilder
                    ->andWhere(sprintf('tsquery(%s.headlineText,:searchQuery) = true', $alias))
                    ->setParameter('searchQuery', $word);
            }
        } else {
            $queryBuilder
                ->andWhere('(' . $orExp . ')')
                ->setParameter($parameterName, strtolower($word) . '%');
        }

        // if the field is a full text field, apply tsquery

        //        dd($queryBuilder->getQuery()->getSQL());
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
                'required' => false,
                'openapi' => [
                    'description' => 'Selects entities where each search term is found somewhere in at least one of the specified properties',
                ],
            ],
        ];
    }
}
