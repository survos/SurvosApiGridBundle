<?php

namespace Survos\ApiGrid\Components;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\GetCollection;
use Psr\Log\LoggerInterface;
use Survos\ApiGrid\Components\Common\TwigBlocksInterface;
use Survos\ApiGrid\Model\Column;
use Survos\ApiGrid\Service\DatatableService;
use Survos\ApiGrid\Service\MeiliService;
use Survos\ApiGrid\State\MeiliSearchStateProvider;
use Survos\ApiGrid\TwigBlocksTrait;
use Survos\InspectionBundle\Services\InspectionService;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Twig\Environment;

#[AsTwigComponent('jsTwig', template: '@SurvosApiGrid/components/js_twig.html.twig')]
class JsTwigComponent implements TwigBlocksInterface
{
    use TwigBlocksTrait;

    public string $caller;
    public string $apiUrl;
    public string $id; // for parsing out the twig blocks
    public function __construct(
        private Environment $twig,
        private LoggerInterface $logger,
    ) {

        //        ='@survos/grid-bundle/api_grid';
    }


}
