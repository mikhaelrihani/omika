<?php

namespace App\Controller\Event;

use App\Repository\Event\EventRecurringRepository;
use App\Service\Event\EventRecurringService;
use App\Service\Event\EventService;
use App\Utils\ApiResponse;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


/**
 * Controller for handling recurring events.
 */
#[Route('/api/event', name: "app_event_")]
class EventRecurringController extends AbstractController
{

    public function __construct(
        private EventRecurringService $eventRecurringService,
        private EntityManagerInterface $em,
        private EventRecurringRepository $eventRecurringRepository,
        private EventService $eventService
    ) {
    }

    //! --------------------------------------------------------------------------------------------
    #[Route("/getOneEventRecurring/{id}", name: "getOneEventRecurring", methods: ["GET"])]
    public function getOneEventRecurring(int $id): JsonResponse
    {
        $eventRecurring = $this->eventRecurringRepository->find($id);

        if (!$eventRecurring) {
            $response = ApiResponse::error("There is no event recurring with this id", null, Response::HTTP_NOT_FOUND);
            return $this->json($response, $response->getStatusCode());
        }

        $response = ApiResponse::success("Event recurring retrieved successfully", ['eventRecurring' => $eventRecurring], Response::HTTP_OK);
        return $this->json($response->getData(), $response->getStatusCode(), [], ['groups' => 'eventRecurring']);
    }


    //! --------------------------------------------------------------------------------------------
    /**
     * Retrieves all recurring events from the database.
     *
     * This method handles the retrieval of all recurring events using the
     * EventRecurringRepository. If no recurring events are found, an error 
     * response with a 404 status is returned. Otherwise, a success response 
     * containing the list of recurring events is returned.
     *
     * @Route("/getAllEventsRecurring", name="getAllEventsRecurring", methods={"GET"})
     *
     * @return JsonResponse The response containing either the recurring events
     *                      or an error message.
     */
    #[Route("/getAllEventsRecurring", name: "getAllEventsRecurring", methods: ["GET"])]
    public function getAllEventsRecurring(): JsonResponse
    {
        $eventsRecurring = $this->eventRecurringRepository->findAll();
        if (!$eventsRecurring) {
            $response = ApiResponse::error("There is no event recurring", null, Response::HTTP_NOT_FOUND);
            return $this->json($response, $response->getStatusCode());
        }
        $response = ApiResponse::success("Events recurring retrieved successfully", ['eventRecurring' => $eventsRecurring], Response::HTTP_OK);
        return $this->json($eventsRecurring, $response->getStatusCode(), [], ['groups' => 'eventRecurring']);
    }

    //! --------------------------------------------------------------------------------------------
    /**
     * Deletes an EventRecurring by its ID.
     * it will delete the parent and all its children with the tags.
     * This method attempts to find an `EventRecurring` by its ID, and if it exists, it removes it from the database.
     * If the `EventRecurring` is not found, a `404 Not Found` response is returned.
     * 
     * @Route("/deleteEventRecurring/{id}", name="deleteEventRecurring", methods={"DELETE"})
     * 
     * @param int $id The ID of the EventRecurring entity to delete.
     * 
     * @return JsonResponse The response indicating whether the deletion was successful or not.
     */
    #[Route("/deleteEventRecurring/{id}", name: "deleteEventRecurring", methods: ["DELETE"])]
    public function deleteEventRecurring(int $id): JsonResponse
    {
        $eventRecurring = $this->eventRecurringRepository->find($id);
        if (!$eventRecurring) {
            $response = ApiResponse::error("There is no event recurring with this id", null, Response::HTTP_NOT_FOUND);
            return $this->json($response, $response->getStatusCode());
        }

        $response = $this->eventRecurringService->deleteRecurringEvent($eventRecurring);

        return $this->json($response, $response->getStatusCode());
    }

    //! --------------------------------------------------------------------------------------------

    #[Route("/updateEventRecurring/{id}", name: "updateEventRecurring", methods: ["PUT"])]
    public function updateEventRecurring(int $id, Request $request): JsonResponse
    {
        $eventRecurring = $this->eventRecurringRepository->find($id);

        if (!$eventRecurring) {
            $response = ApiResponse::error(
                "There is no event recurring with this ID",
                null,
                Response::HTTP_NOT_FOUND
            );
            return $this->json($response, $response->getStatusCode());
        }

        $this->em->beginTransaction();

        try {
            // Étape 1 : Pré-traitement 
            $response = $this->eventRecurringService->handlePreDeleteRecurringEventParent($eventRecurring);
            if (!$response->isSuccess()) {
                $this->em->rollback();
                return $this->json($response, $response->getStatusCode());
            }

            // Étape 2 : Création de l'événement
            $response = $this->eventService->createEvent($request);

            if ($response->getStatusCode() !== 201) {
                $this->em->rollback();
                return $response;
            }

            // Validation de la transaction
            $this->em->commit();

        } catch (Exception $e) {
            // Annulation et retour d'erreur
            $this->em->rollback();
            $response = ApiResponse::error(
                "An error occurred while updating the event recurring: " . $e->getMessage(),
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
            return $this->json($response, $response->getStatusCode());
        }

        // Succès
        return $response;
    }


    //! --------------------------------------------------------------------------------------------

    // #[Route('/toggleImportant/{id}', name: 'toggleImportant', methods: ['post'])]
    // public function toggleImportant(Event $event): JsonResponse
    // {

    // }

    //! --------------------------------------------------------------------------------------------

    // #[Route('/toggleFavorite/{id}', name: 'toggleFavorite', methods: ['post'])]
    // public function toggleFavorite(Event $event): JsonResponse
    // {

    // }

    //! --------------------------------------------------------------------------------------------

    // #[Route('/toggleStatus/{id}', name: 'toggleStatus', methods: ['post'])]
    // public function toggleStatus(Event $event): JsonResponse
    // {

    // }

    //! --------------------------------------------------------------------------------------------

    //removeUserFromAllEvents
}
