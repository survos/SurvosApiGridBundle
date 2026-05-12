<?php

namespace Survos\ApiGridBundle\Components;

use Psr\Log\LoggerInterface;
use Survos\ApiGridBundle\Components\Common\TwigBlocksInterface;
use Survos\ApiGridBundle\Service\DatatableService;
use Survos\ApiGridBundle\TwigBlocksTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
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
        public ?string $stimulusController=null,
        private ?string $class = null,
        private array $filter = [],
    ) {
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
    public string|array|null $layout = null;
    public int $pageLength=50;
    public ?string $apiGetCollectionUrl=null;
    public ?string $apiRoute = null;
    public array $apiRouteParams = [];
    public array  $apiGetCollectionParams = [];
    public bool $trans = true;
    public string|bool|null $domain = null;
    public array $buttons=[]; // for now, simply a keyed list of urls to open

    /** Enable DataTables Select extension — prepends a checkbox column, multi-select. */
    public bool $select = false;

    /**
     * Initial sort, comma-separated "field:asc|desc" pairs.
     * Example: "id:desc"  or  "updatedAt:desc,id:desc".
     * Falls through to DataTables' `order` option. Columns must be `sortable: true`.
     */
    public ?string $defaultOrder = null;

    /** When set, an eye-button appears per row; clicking fetches this route and renders the response in an offcanvas panel. */
    public ?string $showRoute = null;

    /**
     * Server-side bulk actions that POST selected row IDs.
     * Each entry: ['id' => string, 'label' => string, 'url' => string,
     *              'destructive' => bool, 'icon' => ?string, 'confirm' => bool,
     *              'confirmMessage' => ?string]  {count} placeholder in confirmMessage is interpolated.
     * Renders as DataTables Buttons disabled until rows are selected.
     */
    public array $bulkActions = [];

    /** Fully-qualified entity class sent as `className` alongside selected IDs. */
    public ?string $entityClass = null;

    /** CSRF token id used for bulk-action POST. */
    public string $csrfTokenId = 'bulk_action';

    public bool $search = true;
    public string $scrollY = '70vh';
    /** Horizontal scroll instead of responsive column hiding. Mutually exclusive with responsive. */
    public bool $scrollX = false;
//    public array $filter = [];
    public bool $useDatatables = true;

    public ?string $source = null;
    public ?string $style = 'spreadsheet';

    public ?string $locale = null; // shouldn't be necessary, but sanity for testing.

    public ?string $path = null;
    public bool $info = false;
    public ?string $tableId = null;
    public string $tableClasses = '';

    // Filter UI (header search vs ColumnControl in headers vs searchBuilder modal)
    public bool $columnControl = false;
    public bool $searchBuilder = false;


    public function getLocale(): string
    {
        return $this->requestStack->getParentRequest()->getLocale();
    }

    public function getDefaultColumns(): array
    {
        if (!$this->class) {
            return [];
        }

        return $this->datatableService->getSettingsFromAttributes($this->class);
    }

    /**
     * @param string $columnType
     * @return Column[]
     */
    public function getNormalizedColumns(string $columnType='columns'): iterable
    {
        // Settings are inferred from PHP attributes and FieldBundle descriptors.

        $settings = $this->getDefaultColumns();
        if (!$this->columns) {
            $this->columns = array_values(array_map(
                static fn(string $name): array => ['name' => $name],
                array_keys($settings)
            ));
        }
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

    public function mount(string $class,
//                          array $columns=[],
                           ?string $apiRoute=null,
                           ?string $apiGetCollectionUrl=null,
                           array $filter = [],
                           array $buttons = [],
                           bool $meili=false
    )
    {
        $this->filter = $filter;
        $this->buttons = $buttons;
        $this->class = $class;
        $this->apiGetCollectionUrl = $apiGetCollectionUrl;
        if ($apiRoute) {
            throw new \RuntimeException('ApiGrid: apiRoute is no longer supported. Pass apiGetCollectionUrl or rely on api_route(class).');
        }
        if ($meili) {
            throw new \RuntimeException('ApiGrid: the meili DataTables mode was removed. Use ux-search or Meilisearch directly.');
        }
    }

    public function facetColumns(): iterable
    {
        return array_values(array_filter($this->getNormalizedColumns(), function ($column) {
            return $column->inSearchPane || $column->browsable;
        }));

    }

    public function getShowRoute(): ?string
    {
        if (!$this->class) {
            return null;
        }

        $shortName = (new \ReflectionClass($this->class))->getShortName();

        return 'app_' . strtolower($shortName) . '_show';
    }
}
