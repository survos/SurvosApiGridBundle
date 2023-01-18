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
class JsonSearchFilter extends AbstractFilter implements JsonSearchFilterInterface, FilterInterface
{
    use JsonSearchFilterTrait;
    /**
     * Add configuration parameter
     * {@inheritdoc}
     * @param string $searchParameterName The parameter whose value this filter searches for
     */
    public function __construct(ManagerRegistry        $managerRegistry,
                                LoggerInterface $logger = null,
                                array $properties = null,
                                NameConverterInterface $nameConverter = null,
                                private string         $searchParameterName = 'json_search')
    {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }


    /** {@inheritdoc} */
    protected function filterProperty(
        string $property,
        $values,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {


//        dd($property, $values, $this->searchParameterName, $this->isPropertyEnabled($property, $resourceClass), $this->isPropertyMapped($property, $resourceClass));

        if (
            !\is_array($values) ||
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        // @todo: make sure it's a valid property, etc.
//        dd($property, $values, $this->searchParameterName, $this->isPropertyEnabled($property, $resourceClass), $this->isPropertyMapped($property, $resourceClass));
        foreach ($values as $value) {
            [$attribute, $operator, $attrValue] = explode(',', $value, 3);

        }
        $this->addJsonWhere($queryBuilder, $property, $attribute, $operator, $attrValue);
//        dd($queryBuilder->getQuery()->getSQL());

//        $values = $this->normalizeValues($values, $property);


        if (null === $value || $property !== $this->searchParameterName) {
            return;
        }

    }

    private function addJsonWhere(QueryBuilder $queryBuilder,
                                  string $property,
                                  string $attribute,
                                  string $operator,
                                  mixed $value)
    {
        $alias = $queryBuilder->getRootAliases()[0];



        $attrValue = [$attribute => $value];
        $queryBuilder
            ->andWhere($where = sprintf(" (CONTAINS(%s.%s, :attrValue)=TRUE)",
                $alias,
                $property,
                json_encode($attrValue),
                json_encode($attrValue),
            ))
//        dd($where, json_encode($attrValue));

//            ->andWhere(sprintf("JSON_GET_FIELD_AS_TEXT(%s.%s, '%s') = :attrValue", $alias, $property, $attribute))
//            ->andWhere('JSON_GET_OBJECT(s.notes, :attr) = :attrValue')
//            ->setParameter('attr', sprintf("{'%s'}", $attribute))
            ->setParameter('attrValue', $attrValue, 'json');

        $query = $queryBuilder->getQuery();

//        dd($queryBuilder->getQuery()->getDQL(),
//            $query->getParameters(), $query->getParameter('attrValue'),
//            $queryBuilder->getQuery()->getSQL());
//
        return;

        $queryBuilder
            ->andWhere('(' . $orExp . ')')
            ->setParameter($parameterName, strtolower($word) . '%');

        // Build OR expression
        $orExp = $queryBuilder->expr()->orX();
        foreach ($this->getProperties() as $prop => $ignoored) {
            $orExp->add($queryBuilder->expr()->like('LOWER(' . $alias . '.' . $prop . ')', ':' . $parameterName));
        }

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


}
