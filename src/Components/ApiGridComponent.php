<?php

namespace Survos\ApiGrid\Components;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Psr\Log\LoggerInterface;
use Survos\ApiGrid\Model\Column;
use Survos\ApiGrid\Service\DatatableService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;
use Twig\Environment;
use function Symfony\Component\String\u;

#[AsTwigComponent('api_grid', template: '@SurvosApiGrid/components/api_grid.html.twig')]
class ApiGridComponent
{
    public function __construct(
        private Environment $twig,
        private LoggerInterface $logger,
        private RequestStack $requestStack,
        private DatatableService $datatableService,
        public ?string $stimulusController
    ) {
        //        ='@survos/grid-bundle/api_grid';
    }

    public iterable $data;

    public array $columns = [];
    public array $searchBuilderFields = [];

    public ?string $caller = null;
    public array|object|null $schema = null;

    public ?string $class = null;
    public string $dom='lfrtip';
    public int $pageLength=50;
    public string $searchPanesDataUrl;
    public string $apiGetCollectionUrl;

    public array $filter = [];

    public ?string $source = null;

    public ?string $locale = null; // shouldn't be necessary, but sanity for testing.

    public ?string $path = null;

//    public function getLocale(): string
//    {
//        return $this->requestStack->getParentRequest()->getLocale();
//    }
    private function getTwigBlocks(): array
    {
        $customColumnTemplates = [];
        if ($this->caller) {
            //            $template = $this->twig->resolveTemplate($this->caller);
            $sourceContext = $this->twig->getLoader()->getSourceContext($this->caller);
            $path = $sourceContext->getPath();
            $this->path = $path;

            //            dd($template);
            $source = file_get_contents($path);
            //            $this->source = $source;
            //            dd($this->twig);
            // get rid of comments
            $source = preg_replace('/{#.*?#}/', '', $source);

            // this blows up with nested blocks.
            // first, get the component twig
            if (preg_match('/component.*?%}(.*?) endcomponent/ms', $source, $mm)) {
                $twigBlocks = $mm[1];
            } else {
                $twigBlocks = $source;
            }
            if (preg_match_all('/{% block (.*?) %}(.*?){% endblock/ms', $twigBlocks, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    [$all, $columnName, $twigCode] = $m;
                    $customColumnTemplates[$columnName] = trim($twigCode);
                }
            }
        }
        return $customColumnTemplates;
    }

    public function getNormalizedColumns()
    {
        // really we're getting the schema from the PHP Attributes here.
        if ($this->class) {
                $settings = $this->datatableService->getSettingsFromAttributes($this->class);
            } else {
                $settings = []; // really settings should probably be passed in via a json schema or something like that.
            }

        return $this->datatableService->normalizedColumns($settings, $this->columns, $this->getTwigBlocks());
    }

}
