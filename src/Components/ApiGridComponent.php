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
use Symfony\UX\TwigComponent\Attribute\PreMount;
use Twig\Environment;

#[AsTwigComponent('api_grid', template: '@SurvosApiGrid/components/api_grid.html.twig')]
class ApiGridComponent implements TwigBlocksInterface
{
    use TwigBlocksTrait;

    public function __construct(
        private Environment $twig,
        private LoggerInterface $logger,
        private RequestStack $requestStack,
        private DatatableService $datatableService,
        private InspectionService $inspectionService,
        private UrlGeneratorInterface $urlGenerator,
        private IriConverterInterface $iriConverter,
        private ?MeiliService $meiliService=null,
        public ?string $stimulusController=null,
        private bool $meili = false,
        private ?string $class = null,
        private array $filter = [],
        private $collectionRoutes = [],
    ) {
        if ($this->class) {
            $this->setCollectionRoutes($this->inspectionService->getAllUrlsForResource($class));
        } else {
//            assert(false, "missing class");
        }

        //        ='@survos/grid-bundle/api_grid';
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
        assert($this->getClass());
        // @todo: move to compilerpass
        return $this->inspectionService->getAllUrlsForResource($this->getClass());
        return $this->collectionRoutes;
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

    public ?string $caller = null;
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
    public function getNormalizedColumns(string $columnType='columns'): iterable
    {
        if ($this->class) {
            if (!$this->index) {
                $this->index =  $this->meiliService->getPrefixedIndexName(MeiliSearchStateProvider::getSearchIndexObject($this->class));
            }
        }
        // really we're getting the schema from the PHP Attributes here.

        $settings = $this->getDefaultColumns();
        $value= match($columnType) {
            'columns' => $this->datatableService->normalizedColumns($settings, $this->columns, $this->getTwigBlocks()),
            'facet_columns' => $this->datatableService->normalizedColumns($settings, $this->facet_columns, $this->getTwigBlocks())
        };
        return $value;
    }

    public function getModalTemplate(): ?string
    {
        return $this->getTwigBlocks()['_modal']??null;

    }

    public function searchPanesColumns(): int
    {
        $count = 0;
        // count the number, if > 6 we could figured out the best layout
        foreach ($this->normalizedColumns() as $column) {
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

        $this->filter = $filter;
        $this->buttons = $buttons;
//        assert($class == $this->class, "$class <> $this->class");
        $this->class = $class; // ??
//            : $this->iriConverter->getIriFromResource($class, operation: new GetCollection(),
//                context: $context ?? []);


        $routes = $this->inspectionService->getAllUrlsForResource($class);
        // the problem with this is that it always gets the _first_ one.
        if ($apiRoute) {
//            $apiGetCollectionUrl = $this->iriConverter->getIriFromResource($class,  operation: new GetCollection());
//        } else {
            // to get the params
            $urls = $this->inspectionService->getAllUrlsForResource($class);
            $routeKey = $apiRoute ?: array_key_first($urls);
            // the real route is the opname
            assert(array_key_exists($routeKey, $routes), "Missing route $routeKey in " . join(',', array_keys($routes)));
            $route = $routes[$routeKey]['opName'];
            $apiGetCollectionUrl =  $this->urlGenerator->generate($route, $params??[]);
//            dd($this->apiGetCollectionUrl);
        }
        $this->apiGetCollectionUrl = $apiGetCollectionUrl;
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


}
