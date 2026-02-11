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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;
use Twig\Environment;
use Twig\TemplateWrapper;

#[AsTwigComponent('api_grid', template: '@SurvosApiGrid/components/api_grid.html.twig')]
class ApiGridComponent implements TwigBlocksInterface
{
    use TwigBlocksTrait;

    public function __construct(
        private Environment $twig,
        private LoggerInterface $logger,
        private RequestStack $requestStack,
        private DatatableService $datatableService,
        private UrlGeneratorInterface $urlGenerator,
        private IriConverterInterface $iriConverter,
        private ?object $inspectionService=null,
        private ?MeiliService $meiliService=null,
        public ?string $stimulusController=null,
        private bool $meili = false,
        private ?string $class = null,
        private array $filter = [],
        private $collectionRoutes = [],
    ) {
        // Intentionally keep constructor side-effect free.
        // Older versions tried to resolve routes here via InspectionService.
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getFilter(): array
    {
        // @todo: be smarter with what's allowed.  This don't really feel right
        if ($stack = $this->requestStack->getCurrentRequest()) {
            $this->filter = array_merge($this->filter, $stack->query->all());
        }
        return $this->filter;
    }

    public function getCollectionRoutes(): array
    {
        if (!$this->class || !$this->inspectionService || !method_exists($this->inspectionService, 'getAllUrlsForResource')) {
            return [];
        }

        // @todo: move to compiler pass if this stays.
        return $this->inspectionService->getAllUrlsForResource($this->class);
    }

    public function setCollectionRoutes(array $collectionRoutes): void
    {
        $this->collectionRoutes = $collectionRoutes;
    }



    public function setClass(?string $class): self
    {
        $this->class = $class;
        return $this;
    }

    public iterable $data;

    public array $columns = [];
    public array $facet_columns = []; // the facet columns, rendered in the sidebar
    public array $globals = [];
    public array $searchBuilderFields = [];

    public string|TemplateWrapper|null $caller = null;
    public array|object|null $schema = null;

//    public ?string $class = null;
    public ?string $index = null; // name of the meili index
    public string $dom='BQlfrtpP';
    public int $pageLength=50;
    public string $searchPanesDataUrl; // maybe deprecate this?
    public ?string $apiGetCollectionUrl=null;
    public ?string $apiRoute = null;
    public array $apiRouteParams = [];
    public array  $apiGetCollectionParams = [];
    public bool $trans = true;
    public string|bool|null $domain = null;
    public array $buttons=[]; // for now, simply a keyed list of urls to open

    public bool $search = true;
    public string $scrollY = '70vh';
//    public array $filter = [];
    public bool $useDatatables = true;

    public ?string $source = null;
    public ?string $style = 'spreadsheet';

    public ?string $locale = null; // shouldn't be necessary, but sanity for testing.

    public ?string $path = null;
    public bool $info = false;
    public ?string $tableId = null;
    public string $tableClasses = '';

    // Filter UI (SearchPanes sidebar vs ColumnControl in headers)
    public bool $searchPanes = true;
    public bool $columnControl = false;


    public function getLocale(): string
    {
        return $this->requestStack->getParentRequest()->getLocale();
    }

    public function getDefaultColumns(): array
    {
        if ($this->class) {
            $settings = $this->datatableService->getSettingsFromAttributes($this->class);
        } else {
            $settings = []; // really settings should probably be passed in via a json schema or something like that.
        }
        return $settings;
    }

    /**
     * @param string $columnType
     * @return Column[]
     */
    public function getNormalizedColumns(string $columnType='columns'): iterable
    {
        // Only compute a Meili index when Meili is explicitly enabled.
        if ($this->meili && $this->class && !$this->index && $this->meiliService) {
            $this->index = $this->meiliService->getPrefixedIndexName(
                MeiliSearchStateProvider::getSearchIndexObject($this->class)
            );
        }

        // Settings are inferred from PHP attributes (ApiFilter/Facet/MeiliId/etc).

        $settings = $this->getDefaultColumns();
        $value= match($columnType) {
            'columns' => $this->datatableService->normalizedColumns($settings, $this->columns, $this->getTwigBlocks()),
            'facet_columns' => $this->datatableService->normalizedColumns($settings, $this->facet_columns, $this->getTwigBlocks())
        };
        return $value;
    }

    public function getSearchBuilderColumns(): array
    {
        $searchBuilderColumns = [];
        foreach ($this->getNormalizedColumns() as $idx => $column) {
            if ($column->browsable) {
                $searchBuilderColumns[] = $idx+1;
            }
        }
        return $searchBuilderColumns;

    }

    public function getModalTemplate(): ?string
    {
        return $this->getTwigBlocks()['_modal']??null;

    }

    public function searchPanesColumns(): int
    {
        $count = 0;
        // count the number, if > 6 we could figured out the best layout
        foreach ($this->getNormalizedColumns() as $column) {
//            dd($column);
            if ($column->inSearchPane) {
                $count++;
            }
        }
        $count = min($count, 6);
        return $count;
    }

    /**
     * @return array<string, Column>
     */
    public function GridNormalizedColumns(): iterable
    {
        $normalizedColumns = [];
        foreach ($this->columns as $c) {
            if (empty($c)) {
                continue;
            }
            if (is_string($c)) {
                $c = [
                    'name' => $c,
                ];
            }
            assert(is_array($c));
            $column = new Column(...$c);
            if ($column->condition) {
                $normalizedColumns[$column->name] = $column;
            }
        }
        return $normalizedColumns;
    }

    public function mount(string $class,
//                          array $columns=[],
                           ?string $apiRoute=null,
                           ?string $apiGetCollectionUrl=null,
                           array $filter = [],
                           array $buttons = [],
                           bool $meili=false)
        // , string $apiGetCollectionUrl,  array  $apiGetCollectionParams = [])
    {
        // this allows the jstwig templates to compile, but needs to happen earlier.
//        $this->twig->addGlobal('owner', []);
//        dd($this->twig->getGlobals());

//        dd($columns, $meili);
        // if meili, get the index and validate the columns



        $this->filter = $filter;
        $this->buttons = $buttons;
        $this->meili = $meili;
//        assert($class == $this->class, "$class <> $this->class");
        $this->class = $class; // ??
//            : $this->iriConverter->getIriFromResource($class, operation: new GetCollection(),
//                context: $context ?? []);
        // Doctrine-first: prefer passing apiGetCollectionUrl explicitly.
        if ($apiGetCollectionUrl) {
            $this->apiGetCollectionUrl = $apiGetCollectionUrl;
            return;
        }

        // Backward compatibility: apiRoute requires InspectionService to map a "route key" to an operation.
        if ($apiRoute) {
            if (!$this->inspectionService || !method_exists($this->inspectionService, 'getAllUrlsForResource')) {
                throw new \RuntimeException(
                    'ApiGrid: apiRoute requires Survos\\InspectionBundle. Prefer passing apiGetCollectionUrl instead.'
                );
            }

            $routes = $this->inspectionService->getAllUrlsForResource($class);
            $routeKey = $apiRoute;
            if (!array_key_exists($routeKey, $routes)) {
                throw new \RuntimeException(sprintf(
                    'ApiGrid: unknown apiRoute "%s". Known keys: %s',
                    $routeKey,
                    implode(', ', array_keys($routes))
                ));
            }

            $opName = $routes[$routeKey]['opName'] ?? null;
            if (!$opName) {
                throw new \RuntimeException(sprintf('ApiGrid: route metadata missing opName for "%s".', $routeKey));
            }

            $this->apiGetCollectionUrl = $this->urlGenerator->generate($opName);
        }
        //        try {
//        } catch (InvalidArgumentException $exception) {
//            $urls = $this->inspectionService->getAllUrlsForResource($class);
//            dd($urls, $exception);
//        }
//        dd($apiGetCollectionUrl);
//        if (!$apiGetCollectionUrl) {
//            $route = array_key_first($urls);
////            $route = $urls[$meili ? MeiliSearchStateProvider::class : CollectionProvider::class];
//            if ($meili) {
//                $indexName = (new \ReflectionClass($class))->getShortName();
//                $params = ['indexName' => $indexName];
//                if (!$this->apiGetCollectionUrl) {
//                    $this->apiGetCollectionUrl =  $this->urlGenerator->generate($route, $params??[]);
//                }
//            }
//            $this->collectionRoutes = $this->inspectionService->getAllUrlsForResource($class);
//        }
//        dd($this->collectionRoutes, $apiGetCollectionUrl, $this->apiGetCollectionUrl);
        return;

        dd($urls, $class, $meili, $route->getPath());
        return;
        dd(func_get_args());;
        dd($this->apiUrl);
    }

    public function facetColumns(): iterable
    {
        return array_values(array_filter($this->getNormalizedColumns(), function ($column) {
            return $column->inSearchPane || $column->browsable;
        }));

    }


}
