<?php

namespace Survos\ApiGrid\Filter\MeiliSearch;

use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class DataTableFacetsFilter extends AbstractSearchFilter implements FilterInterface
{
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceClassResolverInterface $resourceClassResolver, ?NameConverterInterface $nameConverter = null, private readonly string $orderParameterName = 'filter', ?array $properties = null)
    {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $resourceClassResolver, $nameConverter, $properties);
    }

    public function apply(array $clauseBody, string $resourceClass, ?Operation $operation = null, array $context = []): array {
        if(isset($context['filters']['facets'])) {
            if(is_array($context['filters']['facets'])) {
                $clauseBody['facets'] = array_values($context['filters']['facets']);
            }
        }

        return $clauseBody;
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
