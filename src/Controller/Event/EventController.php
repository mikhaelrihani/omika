<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\Event\EventRepository;
use App\Repository\Event\SectionRepository;
use App\Repository\User\UserRepository;
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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;


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
        private TagService $tagService,
        private ValidatorService $validatorService,
        private EventRepository $eventRepository,
        private SectionRepository $sectionRepository,
        private CurrentUser $currentUser,
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
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
     * Récupère les événements d'une section en fonction du type et de la date d'échéance.
     *
     * @Route("/getEventsBySection/{sectionId}", name="getEventsBySection", methods={"POST"})
     *
     * @param int $sectionId L'identifiant de la section.
     * @param Request $request La requête HTTP contenant les paramètres.
     * @return JsonResponse La réponse JSON avec les événements ou une erreur.
     */
    #[Route('/{id}', name: 'getEvent', methods: ['GET'])]
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
        $data = $this->getValidatedDataEventBySection($sectionId, $request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        [$type, $dueDate, $userId] = $data;

        try {
            // Récupération des événements
            $events = $this->eventRepository->findEventsBySectionTypeAndDueDateForUser($sectionId, $type, $dueDate, $userId);

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
        $responseValidator = $this->validatorService->validateJson($request);
        if (!$responseValidator->isSuccess()) {
            return $this->json($responseValidator->getMessage(), $responseValidator->getStatusCode());
        }
        $response = $this->eventService->createOneEvent($responseValidator->getData());

        if ($response->getData() !== null) {
            $event = $response->getData()[ "event" ];
            $responseTag = $this->tagService->createTag($event);
            if (!$responseTag->isSuccess()) {
                return $this->json([$responseTag->getMessage()], $responseTag->getStatusCode());
            }
            return $this->json(["{$response->getMessage()} and {$responseTag->getMessage()}"], $response->getStatusCode());
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
     * Validate and extract data for event retrieval by section.
     *
     * @param int $sectionId The ID of the section.
     * @param Request $request The incoming HTTP request.
     *
     * @return array|JsonResponse An array with type, dueDate, and userId if validation succeeds, 
     *                            or a JsonResponse with an error message if validation fails.
     */
    private function getValidatedDataEventBySection(int $sectionId, Request $request): array|JsonResponse
    {
        // Vérification de l'existence de la section
        $section = $this->sectionRepository->find($sectionId);
        if (!$section) {
            return $this->json(ApiResponse::error("Section not found", null, Response::HTTP_NOT_FOUND));
        }

        // Définition des contraintes pour la requête JSON
        $constraints = new Assert\Collection([
            'type'    => [
                new Assert\NotBlank(message: "Type is required."),
                new Assert\Choice(choices: ['info', 'task'], message: "Invalid type. Allowed values: 'info', 'task'.")
            ],
            'dueDate' => [
                new Assert\NotBlank(message: "Due date is required."),
                new Assert\Date(message: "Invalid date format. Expected format: 'Y-m-d'")
            ]
        ]);

        // Validation des données
        $response = $this->validatorService->validateJson($request, $constraints);
        if (!$response->isSuccess()) {
            return $this->json($response, $response->getStatusCode());
        }

        // Extraction des données validées
        $type = $response->getData()[ 'type' ];
        $dueDate = new DateTimeImmutable($response->getData()[ 'dueDate' ]);
        $userId = $this->currentUser->getCurrentUser()->getId();

        return [$type, $dueDate, $userId];
    }




    // #[Route('/toggleImportant/{id}', name: 'toggleImportant', methods: ['post'])]
    // public function toggleImportant(Event $event): JsonResponse
    // {

    // }

    // #[Route('/toggleFavorite/{id}', name: 'toggleFavorite', methods: ['post'])]
    // public function toggleFavorite(Event $event): JsonResponse
    // {

    // }

    // #[Route('/toggleStatus/{id}', name: 'toggleStatus', methods: ['post'])]
    // public function toggleStatus(Event $event): JsonResponse
    // {

    // }



    // public function getPublishedEventsByCurrentUser(Event $event): JsonResponse
    // {

    // }

    // public function getDraftEventsByCurrentUser(Event $event): JsonResponse
    // {
    // }



    // public function deleteDraftEventsByCurrentUser(Event $event): JsonResponse
    // {
    // }

    // public function updatePublishedEventsByCurrentUser(Event $event): JsonResponse
    // {

    // }

    //! divers flows

    /**
     * Effectue diverses actions liées aux événements. Ces actions incluent, entre autres :
     * - Récupérer les événements selon des critères (type, section, active_day_range).
     * - Créer un événement.
     * - Créer un service pour la validation des événements.
     * - Ajouter des fonctionnalités pour compter les événements par section.
     * - Modifier un événement par son ID.
     * - Supprimer un événement par son ID.
     * 
     * Ces méthodes seront mises en œuvre ultérieurement.
     *
     * @todo Mettre en œuvre des fonctionnalités supplémentaires pour gérer les événements.
     */
}
