<?php

namespace App\Controller;

use App\Service\Event\CronService as EventCronService;
use App\Service\Event\EventService;
use App\Service\User\CronService as UserCronService;
use App\Service\Media\CronService as MediaCronService;
use App\Service\OPS\CronService as ProductCronService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CronController extends AbstractController
{
    public static bool $isCronRoute = false;// flag statique vérifié dans le onPreUpdate de updatedAt
    private string $cronUsername;

    public function __construct(
        protected EventCronService $eventCronService,
        protected UserCronService $userCronService,
        protected MediaCronService $mediaCronService,
        protected ProductCronService $productCronService,
        protected EventService $eventService,
        protected JWTTokenManagerInterface $jwtManager,
        protected ParameterBagInterface $parameterBag,
        protected EntityManagerInterface $em,

    ) {
        $this->cronUsername = $this->parameterBag->get('cronUsername');
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
        $this::$isCronRoute = true;

        $cronUser = $this->em->getRepository('App\Entity\User\UserLogin')->findOneBy(['email' => $this->cronUsername]);
        $jwt = $this->jwtManager->create($cronUser);

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

        $productResponse = $this->productCronService->load();
        if (!$productResponse->isSuccess()) {
            return $this->json($productResponse->getMessage(), $productResponse->getStatusCode());
        }

        $responseMessage = "Cron job completed successfully.
        {$eventResponse->getMessage()},
        {$userResponse->getMessage()},
        {$mediaResponse->getMessage()},
        {$productResponse->getMessage()}.";

        $response = new JsonResponse([
            'message' => $responseMessage
        ], Response::HTTP_OK);

        $response->headers->set('Authorization', 'Bearer ' . $jwt);

        return $response;
    }


}
