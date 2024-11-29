<?php

namespace App\Controller\Event;

use App\Repository\Event\EventRecurringRepository;
use Doctrine\Common\Collections\ArrayCollection;
use App\Repository\Event\EventRepository;
use App\Repository\Event\SectionRepository;
use App\Repository\User\UserRepository;
use App\Service\Event\EventRecurringService;
use App\Service\Event\EventService;
use App\Service\Event\TagService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


/**
 * Contrôleur API pour gérer les événements.
 *
 * Ce contrôleur permet de gérer les événements via une API REST, y compris la récupération des événements,
 * la validation des droits d'accès, et la gestion des utilisateurs associés à un événement.
 */
#[Route('/api/event', name: "app_event_")]
class EventController extends AbstractController
{
    /**
     * @param EventService $eventService Service pour la gestion des événements.
     * @param EventRepository $eventRepository Repository pour la gestion des entités Event.
     * @param CurrentUser $currentUser Service utilitaire pour obtenir l'utilisateur courant.
     */
    public function __construct(
        private EventService $eventService,
        private EventRecurringService $eventRecurringService,
        private TagService $tagService,
        private ValidatorService $validatorService,
        private EventRepository $eventRepository,
        private SectionRepository $sectionRepository,
        private CurrentUser $currentUser,
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private EventRecurringRepository $eventRecurringRepository
    ) {
    }

    //! --------------------------------------------------------------------------------------------
    /**
     * Récupère tous les événements.
     *
     * Cette méthode retourne une liste de tous les événements existants dans la base de données.
     *
     * @return JsonResponse La réponse JSON contenant tous les événements.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $events = $this->eventRepository->findAll();
        return $this->json($events, 200, [], ['groups' => 'event']);
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Récupère un événement spécifique par son identifiant.
     *
     * @Route("/{id<\d+>}", name="getEvent", methods={"GET"})
     *
     * @param int $id L'identifiant de l'événement à récupérer.
     *
     * @return JsonResponse La réponse contenant l'événement ou un message d'erreur.
     *
     * @throws JsonResponse Une réponse d'erreur si l'événement n'existe pas ou si l'utilisateur n'a pas les droits pour le consulter.
     */
    #[Route('/{id<\d+>}', name: 'getEvent', methods: ['GET'])]
    public function getEvent(int $id): JsonResponse
    {

        $event = $this->eventRepository->find($id);

        if (!$event) {
            $response = ApiResponse::error("There is no event with this id", null, Response::HTTP_NOT_FOUND);
            return $this->json($response, $response->getStatusCode());
        }

        // Vérification de la visibilité de l'événement pour l'utilisateur courant
        $isVisible = $this->eventService->isVisibleForCurrentUser($event);
        if (!$isVisible) {
            $response = ApiResponse::error("You are not allowed to see this event", null, Response::HTTP_FORBIDDEN);
            return $this->json($response, $response->getStatusCode());
        }

        $response = ApiResponse::success("Event retrieved successfully", ['event' => $event], Response::HTTP_OK);
        return $this->json($response->getData(), $response->getStatusCode(), [], ['groups' => 'event']);
    }

    //! --------------------------------------------------------------------------------------------
    /**
     * Retrieve events by section ID, type, and due date.
     *
     * @param int $sectionId The ID of the section.
     * @param Request $request The incoming HTTP request.
     *
     * @return JsonResponse A JSON response with the events or an error message.
     *
     * @Route("/getEventsBySection/{sectionId}", name="getEventsBySection", methods={"POST"})
     */
    #[Route('/getEventsBySection/{sectionId}', name: 'getEventsBySection', methods: ['POST'])]
    public function getEventsBySection(int $sectionId, Request $request): JsonResponse
    {
        // Récupération et validation des données
        $data = $this->eventService->getValidatedDataEventBySection($sectionId, $request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        [$type, $dueDate, $userId] = $data;

        try {
            // Récupération des événements
            $events = $this->eventRepository->findEventsBySectionTypeAndDueDateForUser($sectionId, $type, $dueDate, $userId);
            // Filtrer les events pour traiter le cas is_published is false
            $events = new ArrayCollection($events);
            $events = $events->filter(function ($event): bool {
                if (!$event->isPublished() && $event->getCreatedBy() !== $this->currentUser->getCurrentUser()->getFullName()) {
                    return false;
                }
                return true;
            });
            $response = ApiResponse::success("Events retrieved successfully", ['events' => $events], Response::HTTP_OK);
            return $this->json($response->getData()[ "events" ], $response->getStatusCode(), [], ['groups' => 'event']);
        } catch (Exception $e) {
            $response = ApiResponse::error("An error occurred while retrieving events", null, Response::HTTP_INTERNAL_SERVER_ERROR);
            return $this->json($response, $response->getStatusCode());
        }
    }

    //! --------------------------------------------------------------------------------------------


    /**
     * Delete an event by its ID.
     *
     * This method deletes an event from the database by its ID.
     * The event is removed from the database and the shared users count of the tag is decremented by one.
     *
     * @param int $id The ID of the event to delete.
     *
     * @return JsonResponse A JSON response with a success message or an error message.
     *
     * @Route("/deleteEvent/{id}", name="deleteEvent", methods={"DELETE"})
     */
    #[route('/deleteEvent/{id}', name: 'deleteEvent', methods: ['delete'])]
    public function deleteEvent(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            $response = ApiResponse::error("There is no event with this id", null, Response::HTTP_NOT_FOUND);
            return $this->json($response->getMessage(), $response->getStatusCode());
        }

        $response = $this->eventService->removeEventAndUpdateTagCounters($event);

        return $this->json($response->getMessage(), $response->getStatusCode());
    }

    //! --------------------------------------------------------------------------------------------


    /**
     * Create a new event.
     *
     * This method creates a new event in the database based on the request payload.
     * The event is created and the tags are handled.
     *
     * @param Request $request The HTTP request containing the event data.
     *
     * @return JsonResponse A JSON response with a success message or an error message.
     *
     * @Route("/createEvent", name="createEvent", methods={"POST"})
     */
    #[Route("/createEvent", name: "createEvent", methods: ["POST"])]
    public function createEvent(Request $request): JsonResponse
    {
        return $this->eventService->createEvent($request);
    }


    //! --------------------------------------------------------------------------------------------



    /**
     * Updates an event by deleting the existing one and creating a new one.
     * 
     * This method begins a database transaction, deletes the event identified by its ID, and recreates it based on the request payload.
     * If any operation fails, the transaction is rolled back, and an error response is returned.
     *
     * @param Request $request The HTTP request containing the updated event data.
     * @param int $id The ID of the event to update.
     * 
     * @return Response A JSON response indicating the result of the operation.
     * 
     * @throws Exception If an error occurs during the transaction.
     */
    #[Route('/updateEvent/{id}', name: 'updateEvent', methods: ['PUT'])]
    public function updateEvent(Request $request, int $id): Response
    {
        $this->em->beginTransaction();

        try {
            $response = $this->deleteEvent($id);
            if ($response->getStatusCode() !== 200) {
                $this->em->rollback();
                return $response;
            }
            $response = $this->createEvent($request);
            if ($response->getStatusCode() !== 200) {
                $this->em->rollback();
                return $response;
            }

            $this->em->commit();

            $response = ApiResponse::success("Event updated successfully", null, Response::HTTP_OK);
            return $this->json($response->getMessage(), $response->getStatusCode());

        } catch (Exception $e) {
            $this->em->rollback();
            $response = ApiResponse::error("An error occurred while updating the event", null, Response::HTTP_INTERNAL_SERVER_ERROR);
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Récupère les événements en attente créés par l'utilisateur courant.
     *
     * @Route("/getUserPendingEvents", name="getUserPendingEvents", methods={"GET"})
     *
     * @return JsonResponse La réponse contenant les événements en attente.
     */
    #[Route("/getUserPendingEvents", name: "getUserPendingEvents", methods: ["GET"])]
    public function getUserPendingEvents(): JsonResponse
    {
        $criteria = [
            'createdBy' => $this->currentUser->getCurrentUser()->getFullName(),
            'isPending' => true,
        ];
        return $this->eventService->getEventsByCriteria($criteria);
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Récupère tous les événements créés par l'utilisateur courant.
     *
     * @Route("/getEventsCreatedByUser", name="getEventsCreatedByUser", methods={"GET"})
     *
     * @return JsonResponse La réponse contenant les événements créés par l'utilisateur.
     */
    #[Route("/getEventsCreatedByUser", name: "getEventsCreatedByUser", methods: ["GET"])]
    public function getEventsCreatedByUser(): JsonResponse
    {
        $criteria = [
            'createdBy' => $this->currentUser->getCurrentUser()->getFullName(),
        ];

        return $this->eventService->getEventsByCriteria($criteria);
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
            $response = $this->createEvent($request);

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
