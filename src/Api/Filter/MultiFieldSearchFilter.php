<?php

declare(strict_types=1);

namespace Survos\ApiGridBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Selects entities where each search term is found somewhere
 * in at least one of the specified properties.
 * Search terms must be separated by spaces.
 * Search is case-insensitive.
 * All specified properties type must be string.
 * @package App\Filter
 */
final class MultiFieldSearchFilter extends AbstractFilter implements FilterInterface
{
    /**
     * Add configuration parameter
     * {@inheritdoc}
     * @param string $searchParameterName The parameter whose value this filter searches for
     */
    public function __construct(
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
        private readonly string $searchParameterName = 'search',
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }


    /** {@inheritdoc} */
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if (!\is_scalar($value) || $property !== $this->searchParameterName) {
            return;
        }

        $words = preg_split('/\s+/', trim((string) $value)) ?: [];
        foreach ($words as $word) {
            if ('' === $word) {
                continue;
            }

            $this->addWhere($queryBuilder, $word, $queryNameGenerator->generateParameterName($this->searchParameterName));
        }
    }

    private function addWhere(QueryBuilder $queryBuilder, string $word, string $parameterName): void
    {
        $alias = $queryBuilder->getRootAliases()[0];
        $orExp = $queryBuilder->expr()->orX();

        foreach ($this->configuredProperties() as $prop) {
            $orExp->add($queryBuilder->expr()->like('LOWER(' . $alias . '.' . $prop . ')', ':' . $parameterName));
        }

        if (0 === $orExp->count()) {
            return;
        }

        $queryBuilder
            ->andWhere($orExp)
            ->setParameter($parameterName, '%' . mb_strtolower($word) . '%');
    }


    public function getDescription(string $resourceClass): array
    {
        $props = $this->getProperties();
        if (null === $props) {
            throw new \InvalidArgumentException('Properties must be specified');
        }
        return [
            $this->searchParameterName => [
                'property' => implode(', ', $this->configuredProperties()),
                'type' => 'string',
                'required' => false,
                'openapi' => [
                    'description' => 'Selects entities where each search term is found somewhere in at least one of the specified properties',
                ],
            ],
        ];
    }

    /**
     * ApiFilter properties may be declared as either ['title', 'overview'] or
     * ['title' => true, 'overview' => true]. Normalize both forms.
     *
     * @return list<string>
     */
    private function configuredProperties(): array
    {
        $properties = [];
        foreach ($this->getProperties() ?? [] as $property => $strategy) {
            $properties[] = (string) (is_int($property) ? $strategy : $property);
        }

        return $properties;
    }
}
