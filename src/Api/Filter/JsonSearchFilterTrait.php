<?php

// modelled after RangeFiler

declare(strict_types=1);

namespace Survos\ApiGrid\Api\Filter;

use ApiPlatform\Doctrine\Common\PropertyHelperTrait;
use ApiPlatform\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;

trait JsonSearchFilterTrait
{
    use PropertyHelperTrait;

    /**
     * {@inheritdoc}
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->getProperties();
        if (null === $properties) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        foreach ($properties as $property => $unused) {
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

//            foreach ([$property, "{$property}[]"] as $filterParameterName) {
//                $description[$filterParameterName] = [
//                    'property' => $property,
//                    'type' => 'string',
//                    'required' => false,
//                ];
//            }

            $description += $this->getFilterDescription($property, null);
//            $description += $this->getFilterDescription($property, self::PARAMETER_OPERATIOR);
//            $description += $this->getFilterDescription($property, self::PARAMETER_EQUALS);
//            $description += $this->getFilterDescription($property, self::PARAMETER_BETWEEN);
        }

        return $description;
    }

    abstract protected function getProperties(): ?array;

    abstract protected function getLogger(): LoggerInterface;

    abstract protected function normalizePropertyName(string $property): string;

    /**
     * Gets filter description.
     */
    protected function getFilterDescription(string $fieldName, ?string $operator): array
    {
        $propertyName = $this->normalizePropertyName($fieldName);

        if ($operator) {
            return [
                sprintf('%s[%s]', $propertyName, $operator) => [
                    'property' => $propertyName,
                    'type' => 'string',
                    'required' => false,
                ],
            ];

        } else {
            // not sure how to make this an array...
            return [
                sprintf('%s', $propertyName) => [
                    'property' => $propertyName . '[]',
                    'type' => 'string',
                    'is_collection' => true,
                    'required' => false,
                ],
            ];

        }

    }

    private function normalizeValues(array $values, string $property): ?array
    {
        $operators = [self::PARAMETER_OPERATIOR, self::PARAMETER_BETWEEN, self::PARAMETER_EQUALS];

        foreach ($values as $operator => $value) {
            if (!\in_array($operator, $operators, true)) {
                unset($values[$operator]);
            }
        }

        if (empty($values)) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('At least one valid operator ("%s") is required for "%s" property', implode('", "', $operators), $property)),
            ]);

            return null;
        }

        return $values;
    }

    /**
     * Normalize the values array for between operator.
     */
    private function normalizeBetweenValues(array $values): ?array
    {
        if (2 !== \count($values)) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid format for "[%s]", expected "<min>..<max>"', self::PARAMETER_BETWEEN)),
            ]);

            return null;
        }

        if (!is_numeric($values[0]) || !is_numeric($values[1])) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid values for "[%s]" range, expected numbers', self::PARAMETER_BETWEEN)),
            ]);

            return null;
        }

        return [$values[0] + 0, $values[1] + 0]; // coerce to the right types.
    }

    /**
     * Normalize the value.
     */
    private function normalizeValue(string $value, string $operator): float|int|null
    {
        if (!is_numeric($value)) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid value for "[%s]", expected number', $operator)),
            ]);

            return null;
        }

        return $value + 0; // coerce $value to the right type.
    }
}
