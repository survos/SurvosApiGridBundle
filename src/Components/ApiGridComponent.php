<?php

namespace Survos\ApiGrid\Components;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Psr\Log\LoggerInterface;
use Survos\Grid\Model\Column;
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
        public ?string $stimulusController
    ) {
        //        ='@survos/grid-bundle/api_grid';
    }

    public iterable $data;

    public array $columns = [];

    public ?string $caller = null;

    public string $class;

    public array $filter = [];

    public ?string $source = null;

    public ?string $path = null;

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

    /**
     * @return array<int, Column>
     */
    public function normalizedColumns(): iterable
    {
        //        $normalizedColumns = parent::normalizedColumns();

        //        dd($customColumnTemplates);
        //        dd($template->getBlockNames());
        //        dd($template->getSourceContext());
        //        dd($template->getBlockNames());
        $customColumnTemplates = $this->getTwigBlocks();
        $normalizedColumns = [];
        foreach ($this->columns as $idx => $c) {
            if (empty($c)) {
                continue;
            }
            if (is_string($c)) {
                $c = [
                    'name' => $c,
                ];
            }
            $columnName = $c['name'];
            if (!$block = $c['block'] ?? false) {
                $block = $columnName;
            }
            $fixDotColumnName = str_replace('.', '_', $block);
            if (array_key_exists($fixDotColumnName, $customColumnTemplates)) {
                $c['twigTemplate'] = $customColumnTemplates[$fixDotColumnName];
            }
            assert(is_array($c));
            $column = new Column(...$c);
            $normalizedColumns[] = $column;
            //            $normalizedColumns[$column->name] = $column;
        }
        return $normalizedColumns;
    }
}
