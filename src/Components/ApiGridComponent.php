<?php

namespace Survos\ApiGrid\Components;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use DOMDocument;
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
        //        ='@survos/grid-bundle/api_grid';
    }

    public iterable $data;

    public array $columns = [];
    public array $globals = [];
    public array $searchBuilderFields = [];

    public ?string $caller = null;
    public array|object|null $schema = null;

    public ?string $class = null;
    public ?string $index = null; // name of the meili index
    public string $dom='lfrtip';
    public int $pageLength=50;
    public string $searchPanesDataUrl; // maybe deprecate this?
    public string $apiGetCollectionUrl;

    public array $filter = [];

    public ?string $source = null;

    public ?string $locale = null; // shouldn't be necessary, but sanity for testing.

    public ?string $path = null;

    public function getLocale(): string
    {
        return $this->requestStack->getParentRequest()->getLocale();
    }
    private function getTwigBlocks(): array
    {
        $customColumnTemplates = [];
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

            if (0) {

                if (preg_match('|<twig:api_grid.*?>(.*?)</twig:api_grid>|ms', $source, $mm)) {
                    $twigBlocks = $mm[1];
                    $componentHtml = $mm[0];
                    $componentHtml = <<<END
    <twig:Alert>
        <twig:block name="footer">
            <button class="btn btn-primary">Claim your prize</button>
        </twig:block>
    </twig:Alert>
END;
                    $crawler = new Crawler($componentHtml);
                    $crawler->registerNamespace('twig','fake');
                    foreach (['twig:block', 'alert', 'Alter', 'twig|alert', 'twig|block', 'twig', 'block'] as $hack) {
//                        $crawler->filterXPath($hack)->each(fn(Crawler $node) => dd($node, $node->nodeName(), $source));
                    }

//                    dd($componentHtml);
//                    $componentHtml = "<html>$componentHtml</html>";

                } else {
//                    dd($source);
                    $twigBlocks = $source;
                }

            }

            // this blows up with nested blocks.
            if (preg_match('/component.*?%}(.*?) endcomponent/ms', $source, $mm)) {
                $twigBlocks = $mm[1];
            } else {
                $twigBlocks = $source;
            }

//            dump($twigBlocks);
//            $crawler = new Crawler($twigBlocks);
//            dd($crawler);
//            foreach ($crawler as $domElement) {
//                dump($domElement);
//            }
//            $nodeValues = $crawler->each(function (Crawler $node, $i) {
//                dump($node);
//                return $node->text();
//            });
//
//            dd($path, $source, $sourceContext);
            $componentHtml = str_replace(['twig:', 'xmlns:twig="http://example.com/twig"'], '', $twigBlocks);

            $crawler = new Crawler();
            $crawler->addHtmlContent($componentHtml);
            $allTwigBlocks = [];

            if ($crawler->filterXPath('//block')->count() > 0) {
                $allTwigBlocks = $crawler->filterXPath('//block')->each(function (Crawler $node, $i) {
                    return [$node->attr('name') => $node->html()];
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
