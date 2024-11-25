<?php

namespace App\Controller;

use App\Service\Event\CronService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class CronController extends AbstractController
{
    public function __construct(protected CronService $cronService){
    }

    #[Route('api/cron/load', name: 'app_cron', methods: ['get', 'post'])]
    public function load(): JsonResponse
    {
        return $this->cronService->load();

    }

}
