<?php

namespace App\Controller;

use App\Service\Event\CronService as EventCronService;
use App\Service\User\CronService as UserCronService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CronController extends AbstractController
{
    public function __construct(
        protected EventCronService $eventCronService,
        protected UserCronService $userCronService
    ) {
    }

    #[Route('api/cron/load', name: 'app_cron', methods: ['get', 'post'])]
    public function load(): JsonResponse
    {
        $eventResponse = $this->eventCronService->load();
        if (!$eventResponse->isSuccess()) {
            return $this->json($eventResponse->getMessage(), $eventResponse->getStatusCode());
        }
        $userResponse = $this->userCronService->load();
        if (!$userResponse->isSuccess()) {
            return $this->json($userResponse->getMessage(), $userResponse->getStatusCode());
        }
        return $this->json("Cron job completed successfully. {$eventResponse->getMessage()}, {$userResponse->getMessage()}", Response::HTTP_OK);

    }

}
