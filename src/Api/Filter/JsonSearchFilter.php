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
use function Symfony\Component\String\u;

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
                                ?LoggerInterface        $logger = null,
                                ?array                  $properties = null,
                                ?NameConverterInterface $nameConverter = null,
                                private string         $searchParameterName = 'json_search')
    {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }


    /** {@inheritdoc} */
    protected function filterProperty(
        string                      $property,
                                    $values,
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        ?Operation                   $operation = null,
        array                       $context = []
    ): void
    {


//        dd($property, $values, $this->searchParameterName, $this->isPropertyEnabled($property, $resourceClass), $this->isPropertyMapped($property, $resourceClass));

        if (
            !\is_array($values) ||
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        assert(array_is_list($values), "values must be an list of strings");
            foreach ($values as $value) {
                [$attribute, $operator, $attrValue] = explode(',', $value, 3);
                $this->addJsonWhere($queryBuilder, $property, $attribute, $operator, $attrValue);
            }

//        if (null === $value || $property !== $this->searchParameterName) {
//            return;
//        }

    }

    private function addJsonWhere(QueryBuilder $queryBuilder,
                                  string       $property,
                                  string       $attribute,
                                  string       $operator,
                                  mixed        $value)
    {
        $alias = $queryBuilder->getRootAliases()[0];

        // yet another hack
        $attribute = u($attribute)->before('.')->toString();
//        dd($property, $attribute);
//        $attrValue = [$attribute => $value];

//        $queryBuilder->select(
//            sprintf("JSON_GET_FIELD( %s.%s, '%s') ", $alias, $property, $attribute)
////            sprintf("(CONTAINS( JSON_GET_OBJECT(%s.%s, '{%s}'), :attrValue)=TRUE ",
//            sprintf("IN_ARRAY(:attrValue, JSON_GET_FIELD( %s.%s, '%s')) as x ",
//                $alias, $property, $attribute
//            ))
////            ->setParameter('attrValue', $attrValue, 'json');
////            ->setParameter('attrValue', [$value], 'json');
//            ->setParameter('attrValue', $value); // , 'json');
//
//        ;

        $expr = $queryBuilder->expr()->orX();
        foreach (explode('|', $value) as $vv) {
            $expr->add($dql = sprintf("(JSONB_EXISTS(JSON_GET_FIELD(%s.%s, '%s'), '%s'))=TRUE", $alias, $property, $attribute, $vv));
        }

//            $queryBuilder->select("p.name,
//             (JSONB_EXISTS(JSON_GET_FIELD(p.info, 'languages'), '{$field}')) as speaks, JSON_GET_FIELD_AS_TEXT(p.info, 'languages') as languagesText, JSON_GET_FIELD(p.info, 'languages') as languagesArray, p.info");
        $queryBuilder->andWhere($expr);
//        }
//        $queryBuilder
//            ->andWhere($alias . '.code = :code')
//            ->setParameter('code', "24");


//        $query = $queryBuilder->getQuery();
//        return;
//
//
//        $queryBuilder
////            ->andWhere($where = sprintf(" (TEXT_EXISTS(%s.%s, :attrValue)=TRUE)",
//            ->andWhere($where = sprintf(" TEXT_EXISTS('a','b')",
//                sprintf("%s->'%s'", $alias, $property),
////                $property,
//                json_encode($attrValue),
//                json_encode($attrValue),
//            ))
//            ->setParameter('attrValue', $attrValue, 'json');
//
////            ->andWhere(sprintf("JSON_GET_FIELD_AS_TEXT(%s.%s, '%s') = :attrValue", $alias, $property, $attribute))
////            ->andWhere('JSON_GET_OBJECT(s.notes, :attr) = :attrValue')
////            ->setParameter('attr', sprintf("{'%s'}", $attribute))
////        dd($where, json_encode($attrValue));
//
//
//        $queryBuilder
//            ->andWhere($where = sprintf(" json->'autor' ? 'goitia_francisco'",
////                $alias,
////                $property,
////                json_encode($attrValue),
////                json_encode($attrValue),
//            ))
//
////            ->andWhere(sprintf("JSON_GET_FIELD_AS_TEXT(%s.%s, '%s') = :attrValue", $alias, $property, $attribute))
////            ->andWhere('JSON_GET_OBJECT(s.notes, :attr) = :attrValue')
////            ->setParameter('attr', sprintf("{'%s'}", $attribute))
//            ->setParameter('attrValue', $attrValue, 'json');
//
//
//        $query = $queryBuilder->getQuery();
//
//        // select code, json from instance where (json->'autor')::jsonb ? 'goitia_francisco';
//
//        dd($queryBuilder->getQuery()->getDQL(),
//            $query->getFirstResult(),
//            $property,
//            $attribute,
//            $attrValue,
//            $query->getParameters(), $query->getParameter('attrValue'),
//            $queryBuilder->getQuery()->getSQL());
////
//        return;
//
//        $queryBuilder
//            ->andWhere('(' . $orExp . ')')
//            ->setParameter($parameterName, strtolower($word) . '%');
//
//        // Build OR expression
//        $orExp = $queryBuilder->expr()->orX();
//        foreach ($this->getProperties() as $prop => $ignoored) {
//            $orExp->add($queryBuilder->expr()->like('LOWER(' . $alias . '.' . $prop . ')', ':' . $parameterName));
//        }
//
//        // @todo: this is supposed to be looking for tsquery types!  hack!
//        if ($prop === 'headlineText') {
//            if (strlen($word) > 2) {
//                $queryBuilder
//                    ->andWhere(sprintf('tsquery(%s.headlineText,:searchQuery) = true', $alias))
//                    ->setParameter('searchQuery', $word);
//            }
//        } else {
//            $queryBuilder
//                ->andWhere('(' . $orExp . ')')
//                ->setParameter($parameterName, strtolower($word) . '%');
//        }
//
//        // if the field is a full text field, apply tsquery
//
//        //        dd($queryBuilder->getQuery()->getSQL());
    }


}
