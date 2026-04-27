<?php

declare(strict_types=1);

namespace Survos\ApiGridBundle\Twig;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use Survos\ApiGridBundle\Model\Column;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function Symfony\Component\String\u;

class TwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly ?IriConverterInterface $iriConverter = null,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('array_is_list', fn ($x) => is_array($x) && array_is_list($x)),
            new TwigFilter('is_array',      fn ($x) => is_array($x)),
            new TwigFilter('datatable', [$this, 'datatable'], ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('api_route',      [$this, 'apiCollectionRoute']),
            new TwigFunction('api_item_route', [$this, 'apiItemRoute']),
            new TwigFunction('setAttribute', function (array $object, $attribute, $value) {
                $object[$attribute] = $value;
                return $object;
            }),
            new TwigFunction('col', function (...$params) {
                $newParams = [];
                foreach ($params as $key => $value) {
                    $newParams[u($key)->camel()->toString()] = $value;
                }
                return new Column(...$newParams);
            }, ['is_variadic' => true]),
        ];
    }

    /**
     * Returns the AP collection IRI for a class, e.g. '/api/tenants'.
     * Returns null when AP is not installed or the class has no GetCollection operation.
     */
    public function apiCollectionRoute(object|string $entityOrClass, array $context = []): ?string
    {
        if (!$this->iriConverter) {
            return null;
        }

        try {
            $class = is_object($entityOrClass) ? $entityOrClass::class : $entityOrClass;
            return $this->iriConverter->getIriFromResource($class, operation: new GetCollection(), context: $context);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Returns the AP item IRI for an entity instance, e.g. '/api/tenants/tac-shack'.
     */
    public function apiItemRoute(object $entity): ?string
    {
        if (!$this->iriConverter) {
            return null;
        }

        try {
            return $this->iriConverter->getIriFromResource($entity);
        } catch (\Throwable) {
            return null;
        }
    }

    public function datatable(mixed $data): string
    {
        return 'For now, call grid instead.';
    }
}
