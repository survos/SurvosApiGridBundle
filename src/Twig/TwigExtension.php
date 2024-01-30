<?php

namespace Survos\ApiGrid\Twig;

use ApiPlatform\Api\IriConverterInterface;
//use ApiPlatform\Core\Api\IriConverterInterface as LegacyIriConverterInterface;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\GetCollection;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function Symfony\Component\String\u;

class TwigExtension extends AbstractExtension
{
    public function __construct()
    {
    }

    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('array_is_list', fn($x) => array_is_list($x)),
            new TwigFilter('datatable', [$this, 'datatable'], [
                'needs_environment' => true,
                'is_safe' => ['html'],
            ]),
        ];
    }


    public function getFunctions(): array
    {
        return [
            new TwigFunction('setAttribute', function (array $object, $attribute, $value) {
                $object[$attribute] = $value;
                return $object;
            }),
        ];
    }

    public function datatable($data)
    {
        return "For now, call grid instead.";
    }

}
