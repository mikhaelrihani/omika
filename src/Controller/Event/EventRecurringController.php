<?php

namespace App\Controller\Event;

use App\Repository\Event\EventRecurringRepository;
use App\Service\Event\EventService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/api/eventRecurring', name: "app_eventRecurring_")]
class EventRecurringController extends AbstractController
{
    public function __construct(private EventService $eventService, private EventRecurringRepository $eventRecurringRepository)
    {
    }
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $eventRecurrings = $this->eventRecurringRepository->findAll();
        return $this->json($eventRecurrings, 200, [], ['groups' => 'eventRecurring']);
    }
    #[Route('/{id}', name: 'getEventRecurring', methods: ['GET'])]
    public function getEventRecurring(int $id): JsonResponse
    {
        $eventRecurring = $this->eventRecurringRepository->find($id);
        return $this->json($eventRecurring, 200, [], ['groups' => 'eventRecurring']);
    }
}


