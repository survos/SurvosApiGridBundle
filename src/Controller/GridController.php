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

class GridController extends AbstractController
{
    protected $workflowRegistry;

    protected $helper;

    public function __construct(
        private ?ChartBuilderInterface $chartBuilder = null,
    )
    {
//        $this->helper = $helper;
    }


    #[Route('/columns/{entity}', name: 'survos_grid_columns')]
    public function columns(Request $request, string $entity)
    {
        // inspect the entity and colum?
        dd($entity);
        return $this->render("@SurvosWorkflow/index.html.twig", [
            'configs' => $this->helper->getWorkflowConfiguration(),
            'workflowsGroupedByClass' => $workflowsGroupedByClass,
            'workflowsByCode' => $workflowsGroupedByCode,
        ]);
    }

}
