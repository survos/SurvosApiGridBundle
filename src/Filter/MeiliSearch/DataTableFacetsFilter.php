<?php

namespace Survos\ApiGrid\Filter\MeiliSearch;

use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\PropertyInfo\Type;

final class DataTableFacetsFilter extends AbstractSearchFilter implements FilterInterface
{
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceClassResolverInterface $resourceClassResolver, ?NameConverterInterface $nameConverter = null, private readonly string $orderParameterName = 'filter', ?array $properties = null)
    {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $resourceClassResolver, $nameConverter, $properties);
    } 

    public function apply(array $clauseBody, string $resourceClass, ?Operation $operation = null, array $context = []): array {
        if(isset($context['filters']['facets'])) {
            $clauseBody['facets'] = $context['filters']['facets'];//implode(",",$context['filters']['facets']);
        }

        return $clauseBody;
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}