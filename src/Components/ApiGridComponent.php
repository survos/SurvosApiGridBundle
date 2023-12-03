<?php

namespace Survos\ApiGrid\Components;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Psr\Log\LoggerInterface;
use Survos\ApiGrid\Model\Column;
use Survos\ApiGrid\Service\DatatableService;
use Symfony\Component\DomCrawler\Crawler;
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
        $this->filter = $this->requestStack->getCurrentRequest()->query->all();
        //        ='@survos/grid-bundle/api_grid';
    }

    public iterable $data;
    public ?string $facets = 'left'; // left,right,top,button,null

    public array $columns = [];
    public array $globals = [];
    public array $searchBuilderFields = [];

    public ?string $caller = null;
    public array|object|null $schema = null;

    public ?string $class = null;
    public ?string $index = null; // name of the meili index
    public string $dom='lfrtipP';
    public int $pageLength=50;
    public string $searchPanesDataUrl; // maybe deprecate this?
    public string $apiGetCollectionUrl;
    public bool $trans = true;
    public string|bool|null $domain = null;

    public bool $search = true;
    public string $scrollY = '70vh';
    public array $filter = [];
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
    private function getTwigBlocks(): array
    {
        $customColumnTemplates = [];
        $allTwigBlocks = [];
        if ($this->caller) {
            //            $template = $this->twig->resolveTemplate($this->caller);
            $sourceContext = $this->twig->getLoader()->getSourceContext($this->caller);
            $path = $sourceContext->getPath();
            $this->path = $path;
//            dd($sourceContext, $sourceContext->getCode());

            //            dd($template);


            //            $this->source = $source;
            //            dd($this->twig);
            // get rid of comments
            $source = file_get_contents($path);
            $source = preg_replace('/{#.*?#}/', '', $source);

            // first, get the component twig

//            if (0)
//            {
//
/*                if (preg_match('|<twig:api_grid.*?>(.*?)</twig:api_grid>|ms', $source, $mm)) {*/
//                    $twigBlocks = $mm[1];
//                    $componentHtml = $mm[0];
//                    $componentHtml = <<<END
//    <twig:Alert>
//        <twig:block name="footer">
//            <button class="btn btn-primary">Claim your prize</button>
//        </twig:block>
//    </twig:Alert>
//END;
//                    $crawler = new Crawler($componentHtml);
//                    $crawler->registerNamespace('twig','fake');
//                    foreach (['twig:block', 'alert', 'Alter', 'twig|alert', 'twig|block', 'twig', 'block'] as $hack) {
////                        $crawler->filterXPath($hack)->each(fn(Crawler $node) => dd($node, $node->nodeName(), $source));
//                    }
//
////                    dd($componentHtml);
////                    $componentHtml = "<html>$componentHtml</html>";
//
//                } else {
////                    dd($source);
//                    $twigBlocks = $source;
//                }
//
//            }

            // this blows up with nested blocks.  Also, issue with {% block title %}
            if (preg_match('/component.*?%}(.*?) endcomponent/ms', $source, $mm)) {
                $twigBlocks = $mm[1];
            } else {
                $twigBlocks = $source;
            }

            $componentHtml = str_replace(['twig:', 'xmlns:twig="http://example.com/twig"'], '', $source);

            $crawler = new Crawler();
            $crawler->addHtmlContent($componentHtml);
//            dd($componentHtml, $twigBlocks);
            $allTwigBlocks = [];

            if ($crawler->filterXPath('//api_grid')->count() > 0) {
                $twigBlocks = $crawler->filterXPath('//api_grid')->each(function (Crawler $node, $i) {
                    return urldecode($node->html());
                });
                if(is_array($twigBlocks)) {
                    $twigBlocks = $twigBlocks[0];
                }
            } else {
                $twigBlocks = $source;
            }
            if ($crawler->filterXPath('//block')->count() > 0) {

                $allTwigBlocks = $crawler->filterXPath('//block')->each(function (Crawler $node, $i) {
//                    https://stackoverflow.com/questions/15133541/get-raw-html-code-of-element-with-symfony-domcrawler
                    $blockName = $node->attr('name');
                    $html = rawurldecode($node->html());
                    // hack for twig > and <
                    $html = str_replace(['&lt;','&gt;'], ['<', '>'], $html);
                    return [$blockName => $html];
                });
            }


            if (preg_match_all('/{% block (.*?) %}(.*?){% endblock/ms', $twigBlocks, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    [$all, $columnName, $twigCode] = $m;
                    $customColumnTemplates[$columnName] = trim($twigCode);
                }
            }
        }
        foreach($allTwigBlocks as $allTwigBlock) {
            foreach ($allTwigBlock as $key => $value) {
                $customColumnTemplates[$key] = $value;
            }
        }
//        dd(array_keys($customColumnTemplates), $customColumnTemplates);

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


}
