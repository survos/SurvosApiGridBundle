<?php

declare(strict_types=1);

namespace Survos\ApiGridBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminBrowseController extends AbstractController
{
    #[Route('/admin/browse', name: 'survos_admin_browse')]
    public function browse(Request $request): Response
    {
        $class = $request->query->getString('class') ?: null;
        $label = $request->query->getString('label')
            ?: ($class ? (new \ReflectionClass($class))->getShortName() : 'Browse');

        return $this->render('@SurvosApiGrid/admin/browse.html.twig', [
            'class' => $class,
            'label' => $label,
        ]);
    }
}
