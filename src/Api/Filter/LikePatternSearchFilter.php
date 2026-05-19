<?php

declare(strict_types=1);

namespace Survos\ApiGridBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Case-insensitive text search where % in the query value controls the match.
 *
 * Examples:
 *  ?title=%wars%  contains
 *  ?title=wars%   starts with
 *  ?title=%wars   ends with
 *  ?title=wars    exact
 */
final class LikePatternSearchFilter extends AbstractFilter implements FilterInterface
{
    public function __construct(
        ?ManagerRegistry $managerRegistry = null,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (
            null === $value
            || !$this->isPropertyEnabled($property, $resourceClass)
            || !$this->isPropertyMapped($property, $resourceClass, true)
        ) {
            return;
        }

        $values = is_array($value) ? $value : [$value];
        $values = array_values(array_filter($values, static fn (mixed $v): bool => is_scalar($v) && trim((string) $v) !== ''));
        if ($values === []) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;
        $associations = [];
        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field, $associations] = $this->addJoinsForNestedProperty(
                $property,
                $alias,
                $queryBuilder,
                $queryNameGenerator,
                $resourceClass,
                Join::INNER_JOIN,
            );
        }

        $metadata = $this->getNestedMetadata($resourceClass, $associations);
        if (!$metadata->hasField($field)) {
            return;
        }

        $or = $queryBuilder->expr()->orX();
        foreach ($values as $rawValue) {
            $pattern = $this->normalizePattern((string) $rawValue);
            if ($pattern === '') {
                continue;
            }

            $parameterName = $queryNameGenerator->generateParameterName($field);
            $fieldExpression = sprintf('LOWER(%s.%s)', $alias, $field);

            if (str_contains($pattern, '%')) {
                $or->add(sprintf("%s LIKE LOWER(:%s) ESCAPE '\\'", $fieldExpression, $parameterName));
                $queryBuilder->setParameter($parameterName, $pattern);
            } else {
                $or->add(sprintf('%s = LOWER(:%s)', $fieldExpression, $parameterName));
                $queryBuilder->setParameter($parameterName, $pattern);
            }
        }

        if ($or->count() > 0) {
            $queryBuilder->andWhere($or);
        }
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];
        foreach (array_keys($this->getProperties() ?? []) as $property) {
            $description[$this->normalizePropertyName((string) $property)] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'openapi' => [
                    'description' => 'Case-insensitive LIKE pattern. Use %value%, value%, %value, or value for contains, starts, ends, or exact.',
                ],
            ];
        }

        return $description;
    }

    private function normalizePattern(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return str_replace(['\\', '_'], ['\\\\', '\\_'], $value);
    }
}
