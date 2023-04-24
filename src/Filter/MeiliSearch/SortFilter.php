<?php

namespace Survos\ApiGrid\Filter\MeiliSearch;

use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class SortFilter extends AbstractSearchFilter implements FilterInterface
{
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceClassResolverInterface $resourceClassResolver, ?NameConverterInterface $nameConverter = null, private readonly string $orderParameterName = 'sort', ?array $properties = null)
    {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $resourceClassResolver, $nameConverter, $properties);
    }

    public const DIRECTION_ASC = 'ASC';
    public const DIRECTION_DESC = 'DESC';

    public function apply(array $clauseBody, string $resourceClass, ?Operation $operation = null, array $context = []): array {

        if (!\is_array($properties = $context['filters'][$this->orderParameterName] ?? [])) {
            return $clauseBody;
        }

        $orders = [];
        foreach ($properties as $property => $direction) {
            [$type] = $this->getMetadata($resourceClass, $property);

            if (!$type) {
                continue;
            }

            if (empty($direction) && null !== $defaultDirection = $this->properties[$property] ?? null) {
                $direction = $defaultDirection;
            }

            if (!\in_array($direction = strtolower($direction), ['asc', 'desc'], true)) {
                continue;
            }

            $property = null === $this->nameConverter ? $property : $this->nameConverter->normalize($property, $resourceClass, null, $context);
            $orders[] = $property.":".$direction;
        }

        if (!$orders) {
            return $clauseBody;
        }

        return array_merge_recursive($clauseBody, [$this->orderParameterName => $orders]);
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->getProperties($resourceClass) as $property) {
            [$type] = $this->getMetadata($resourceClass, $property);

            if (!$type) {
                continue;
            }

            $description["$this->orderParameterName[$property]"] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        strtolower(self::DIRECTION_ASC),
                        strtolower(self::DIRECTION_DESC),
                    ],
                ]
            ];
        }

        return $description;
    }
}