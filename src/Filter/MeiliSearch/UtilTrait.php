<?php

namespace Survos\ApiGrid\Filter\MeiliSearch;

use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\Exception\PropertyNotFoundException;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Symfony\Component\TypeInfo\Type;
/**
 * Field datatypes helpers.
 *
 * @internal
 *
 */
trait UtilTrait
{
    private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory;

    private readonly ResourceClassResolverInterface $resourceClassResolver;

    /**
     * Is the decomposed given property of the given resource class potentially mapped as a nested field in Elasticsearch?
     */
    private function isNestedField(string $resourceClass, string $property): bool
    {
        return null !== $this->getNestedFieldPath($resourceClass, $property);
    }

    /**
     * Get the nested path to the decomposed given property (e.g.: foo.bar.baz => foo.bar).
     */
    private function getNestedFieldPath(string $resourceClass, string $property): ?string
    {
        $properties = explode('.', $property);
        $currentProperty = array_shift($properties);

        if (!$properties) {
            return null;
        }

        try {
            $propertyMetadata = $this->propertyMetadataFactory->create($resourceClass, $currentProperty);
        } catch (PropertyNotFoundException) {
            return null;
        }

        // TODO: 3.0 allow multiple types
        $type = $propertyMetadata->getBuiltinTypes()[0] ?? null;

        if (null === $type) {
            return null;
        }

        if (
            Type::BUILTIN_TYPE_OBJECT === $type->getBuiltinType()
            && null !== ($nextResourceClass = $type->getClassName())
            && $this->resourceClassResolver->isResourceClass($nextResourceClass)
        ) {
            $nestedPath = $this->getNestedFieldPath($nextResourceClass, implode('.', $properties));

            return null === $nestedPath ? $nestedPath : "$currentProperty.$nestedPath";
        }

        if (
            null !== ($type = $type->getCollectionValueTypes()[0] ?? null)
            && Type::BUILTIN_TYPE_OBJECT === $type->getBuiltinType()
            && null !== ($className = $type->getClassName())
            && $this->resourceClassResolver->isResourceClass($className)
        ) {
            return $currentProperty;
        }

        return null;
    }
}
