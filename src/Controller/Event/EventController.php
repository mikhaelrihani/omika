<?php

namespace App\Controller\Event;


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
use DateTimeImmutable;
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
        private UserRepository $userRepository
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
            return $this->json($response);
        }

        // Vérification de la visibilité de l'événement pour l'utilisateur courant
        $isVisible = $this->eventService->isVisibleForCurrentUser($event);
        if (!$isVisible) {
            $response = ApiResponse::error("You are not allowed to see this event", null, Response::HTTP_FORBIDDEN);
            return $this->json($response);
        }

        $response = ApiResponse::success("Event retrieved successfully", ['event' => $event]);
        return $this->json($response->getData(), 200, [], ['groups' => 'event']);
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

        $response = $this->tagService->decrementSharedUsersTagCountByOne($event);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }

        $this->em->remove($event);
        $this->em->flush();

        $response = ApiResponse::success("Event with id = {$id} has been deleted successfully", null, Response::HTTP_OK);
        return $this->json($response->getMessage(), $response->getStatusCode());
    }

    //! --------------------------------------------------------------------------------------------


    /**
     * Create an event.
     *
     * This method creates a new event in the database.
     * The event data is extracted from the incoming HTTP request.
     * The event cannot be duplicated.
     * Tags are created for the event.
     *
     * @param Request $request The incoming HTTP request.
     *
     * @return JsonResponse A JSON response with a success message or an error message.
     *
     * @Route("/createEvent", name="createEvent", methods={"POST"})
     */
    #[Route("/createEvent", name: "createEvent", methods: ["POST"])]
    public function createEvent(Request $request): JsonResponse
    {
        $response = $this->validatorService->validateJson($request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
      if ($dueDate !== null) {
            $response = $this->eventService->createOneEvent($response->getData());

            if ($response->getData() !== null) {
                $event = $response->getData()[ "event" ];
                $responseTag = $this->tagService->createTag($event);
                if (!$responseTag->isSuccess()) {
                    return $this->json([$responseTag->getMessage()], $responseTag->getStatusCode());
                }
                return $this->json(["{$response->getMessage()} and {$responseTag->getMessage()}"], $response->getStatusCode());
            }
        } else {
            $response = $this->eventRecurringService->createOneEventRecurringParent($response->getData());
            if ($response->getData() !== null) {
                $eventRecurringParent = $response->getData()[ "eventRecurringParent" ];
                $events = $this->eventRecurringService->createChildrens($eventRecurringParent);
                $response = ApiResponse::success(
                    "Event Recurring created successfully with its {$events->count()} children and related Tags.",
                    ['eventRecurring' => $eventRecurringParent, "events" => $events],
                    Response::HTTP_CREATED
                );

                return $this->json(["{$response->getMessage()}"], $response->getStatusCode());
            }

        }

        return $this->json([$response->getMessage()], $response->getStatusCode());
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
