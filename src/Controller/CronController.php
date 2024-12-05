<?php

namespace App\Controller;

use App\Service\Event\CronService as EventCronService;
use App\Service\User\CronService as UserCronService;
use App\Service\Media\CronService as MediaCronService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CronController extends AbstractController
{
    public function __construct(
        protected EventCronService $eventCronService,
        protected UserCronService $userCronService,
        protected MediaCronService $mediaCronService
    ) {
    }


    /**
     * Execute the cron jobs for both event and user-related operations.
     *
     * - This endpoint triggers cron tasks provided by `eventCronService` and `userCronService`.
     * - Handles responses from both services and returns appropriate JSON responses based on their success or failure.
     *
     * @Route("api/cron/load", name="app_cron", methods={"GET", "POST"})
     *
     * @return JsonResponse The response indicating success or failure of the cron jobs.
     */
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

        $mediaResponse = $this->mediaCronService->load();
        if (!$mediaResponse->isSuccess()) {
            return $this->json($mediaResponse->getMessage(), $mediaResponse->getStatusCode());
        }

        return $this->json(
            "Cron job completed successfully. {$eventResponse->getMessage()}, {$userResponse->getMessage()}, {$mediaResponse->getMessage()}.",
            Response::HTTP_OK
        );
    }


}
