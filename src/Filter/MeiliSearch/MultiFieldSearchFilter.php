<?php

namespace Survos\ApiGrid\Filter\MeiliSearch;

use Survos\ApiGrid\Filter\MeiliSearch\FilterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use ApiPlatform\Metadata\InvalidArgumentException;

/**
 * Selects entities where each search term is found somewhere
 * in at least one of the specified properties.
 * Search terms must be separated by spaces.
 * Search is case-insensitive.
 * All specified properties type must be string.
 * @package App\Filter
 */
class MultiFieldSearchFilter  extends AbstractSearchFilter implements FilterInterface
{
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceClassResolverInterface $resourceClassResolver, ?NameConverterInterface $nameConverter = null, private string  $searchParameterName = 'search', ?array $properties = null)
    {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $resourceClassResolver, $nameConverter, $properties);
    }

    public function apply(array $clauseBody, string $resourceClass, ?Operation $operation = null, array $context = []): array {

        if(!isset($context['filters']['facet_filter'])) {
            return $clauseBody;
        }
        //return $clauseBody;
        $facetFilter = isset($clauseBody['filter'])?$clauseBody['filter']." AND ":"";
        foreach($context['filters']['facet_filter'] as $filter) {
            $words = explode(',', $filter);
            if(count($words) < 3) {
                return $clauseBody;
            }
            $key = $words[0];
            $values = explode('|', $words[2]);
            $condition = "";
            foreach ($values as $value) {
                $condition .= " ".$key." = '".$value."' OR ";
            }
            $facetFilter .=" ( ".rtrim($condition,"OR "). " ) AND";
        }

        $clauseBody['filter'] = rtrim($facetFilter, "AND");
        return $clauseBody;
    }


    public function getDescription(string $resourceClass): array
    {
        $props = $this->properties;
        if (null === $props) {
            throw new \InvalidArgumentException('Properties must be specified');
        }
        return [
            $this->searchParameterName => [
                'property' => implode(', ', array_keys($props)),
                'type' => 'string',
                'required' => false,
                'is_collection' => true,
                'openapi' => [
                    'description' => 'Selects entities where each search term is found somewhere in at least one of the specified properties',
                ],
            ],
        ];
    }
}
