<?php

namespace Survos\ApiGridBundle\Components;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\GetCollection;
use Psr\Log\LoggerInterface;
use Survos\ApiGridBundle\Components\Common\TwigBlocksInterface;
use Survos\ApiGridBundle\Model\Column;
use Survos\ApiGridBundle\Service\DatatableService;
use Survos\ApiGridBundle\Service\MeiliService;
use Survos\ApiGridBundle\State\MeiliSearchStateProvider;
use Survos\ApiGridBundle\TwigBlocksTrait;
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
