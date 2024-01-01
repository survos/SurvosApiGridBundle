<?php

namespace Survos\ApiGrid\Controller;

use Survos\ApiGrid\Service\MeiliService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class MeiliController extends AbstractController
{
    protected $helper;

    public function __construct(
        private MeiliService  $meili,
        private ?ChartBuilderInterface $chartBuilder = null,
    )
    {
//        $this->helper = $helper;
    }

    #[Route(path: '/facet/{indexName}/{fieldName}/{max}', name: 'survos_facet_show', methods: ['GET'])]
    public function facet(string $indexName, string $fieldName, int $max = 25): Response
    {
        $index = $this->meili->getIndex($indexName);
        $data = $index->rawSearch("",['limit' => 0, 'facets' => [$fieldName]]);

        $facetDistributionCounts = $data['facetDistribution'][$fieldName]??[];
//        $translations = $projectService->getNonObjectTranslations($project->getCode(), $field->getCoreCode(), $locale); // , '=');
        $counts = [];
            foreach ($facetDistributionCounts as $label => $count) {
                $counts[] = [
                    'label' => $label,
                    'count' => $count
                ];
            }
        $chartData = [];
        foreach (array_slice($counts, 0, $max) as $count) {
            $chartData[$count['label'] ?? $count['code']] = $count['count'];
        }
        $chart = null;
        if ($this->chartBuilder) {
            $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);
            $chart->setData([
                'labels' => array_keys($chartData),
                'datasets' => [
                    [
                        'label' => 'Data Distribution',
                        'backgroundColor' => array_map(fn ($x) => sprintf('rgb(%d, %d, %d', random_int(0, 255), random_int(0, 255), random_int(0, 255)), array_values($chartData)),
                        'borderColor' => 'rgb(255, 99, 132)',
                        'data' => array_values($chartData),
                    ],
                ],
            ]);

            $chart->setOptions([
                'maintainAspectRatio' => false,
            ]);

        }

        return $this->render('@SurvosApiGrid/facet.html.twig', get_defined_vars() + [
                'tableData' => $counts,
                'chartData' => $chartData,
                'chart' => $chart
            ]);
    }

    #[Route(path: '/stats/{indexName}', name: 'survos_index_stats', methods: ['GET'])]
    public function stats(
        string  $indexName,
        Request $request
    ): Response
    {
        $index = $this->meili->getIndex($indexName);
        $stats = $index->stats();
        // idea: meiliStats as a component?
        return $this->render('@SurvosApiGrid/stats.html.twig', [
            'indexName' => $indexName,
            'settings' => $index->getSettings(),
            'stats' => $stats
        ]);

        // Get the base URL
//        $url = "/api/projects";//.$indexName;
        $url = "/api/" . $indexName;
        $queryParams = ['limit' => 0, 'offset' => 0, '_index' => false];
        $queryParams['_locale'] = $translator->getLocale();
        $settings = $index->getSettings();
        foreach ($settings['filterableAttributes'] as $filterableAttribute) {
            $queryParams['facets'][$filterableAttribute] = 1;
        }
        $queryParams = http_build_query($queryParams);

        $data = $client->request('GET', $finalUrl = $baseUrl . $url . "?" . $queryParams, [
            'headers' => [
                'Content-Type' => 'application/ld+json;charset=utf-8',
            ]
        ]);

        dd($finalUrl, $data->getStatusCode());
        assert($index);
        return $this->render('meili/stats.html.twig', [
            'stats' => $index->stats(),
            'settings' => $index->getSettings()
        ]);


    }


    #[Route('/column/{indexName}/{fieldCode}', name: 'survos_grid_column')]
    public function column(Request $request, string $indexName, string $fieldCode)
    {
        $index = $this->meili->getIndex($indexName);
        $settings = $index->getSettings();
        dd($indexName, $settings);
        // inspect the entity and colum?
        // this gets the facet data from meili, though it could get it from a dedicated Field entity in this bundle
//        dd($fieldCode, $index);

        return $this->render("@SurvosApiGrid/facet.html.twig", [
            'configs' => $this->helper->getWorkflowConfiguration(),
            'workflowsGroupedByClass' => $workflowsGroupedByClass,
            'workflowsByCode' => $workflowsGroupedByCode,
        ]);
    }


}
