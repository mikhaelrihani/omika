<?php

namespace App\Controller\Event;

use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\Event\EventRepository;
use App\Repository\Event\SectionRepository;
use App\Service\Event\EventService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\CurrentUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        private ValidatorService $validatorService,
        private EventRepository $eventRepository,
        private SectionRepository $sectionRepository,
        private CurrentUser $currentUser,
        private EntityManagerInterface $em
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

        // Vérifie si l'utilisateur a les droits d'accès
        $isAllowed = $this->eventService->isAllowed($event);
        if (!$isAllowed) {
            $response = ApiResponse::error("You are not allowed to see this event", null, Response::HTTP_FORBIDDEN);
            return $this->json($response);
        }

        $response = ApiResponse::success("Event retrieved successfully", ['event' => $event]);
        return $this->json($response, 200, [], ['groups' => 'event']);
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
        // Vérification utilisateur
        if (!$this->getUser()->isEnabled()) {
            return $this->json(ApiResponse::error("User is not allowed because disabled", null, Response::HTTP_FORBIDDEN));
        }

        // Récupération et validation des données
        [$type, $dueDate, $userId] = $this->getValidatedDataEventBySection($sectionId, $request);
        try {
            // Récupération des événements
            $events = $this->eventRepository->findEventsBySectionTypeAndDueDateForUser($sectionId, $type, $dueDate, $userId);

            $response = ApiResponse::success("Events retrieved successfully", ['events' => $events], Response::HTTP_OK);
            return $this->json($response->getData()[ "events" ], $response->getStatusCode(), [], ['groups' => 'event']);
        } catch (\Exception $e) {
            $response = ApiResponse::error("An error occurred while retrieving events", null, Response::HTTP_INTERNAL_SERVER_ERROR);
            return $this->json($response, $response->getStatusCode());
        }
    }

    //! --------------------------------------------------------------------------------------------

    #[route('/deleteEvent/{id}', name: 'deleteEvent', methods: ['delete'])]
    public function deleteEvent(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event) {
            $response = ApiResponse::error("There is no event with this id", null, Response::HTTP_NOT_FOUND);
            return $this->json($response->getMessage(), $response->getStatusCode());
        }

        $this->em->remove($event);
        $this->em->flush();

        $response = ApiResponse::success("Event with id = {$id} has been deleted successfully", null, Response::HTTP_OK);
        return $this->json($response->getMessage(), $response->getStatusCode());
    }

 //! --------------------------------------------------------------------------------------------












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

    // #[Route('/getEventsByDate', name: 'getEventsByDate', methods: ['post'])]
    // public function getEventsByDate(DateTimeImmutable $date): JsonResponse
    // {
    //     $user = $this->currentUser->getCurrentUser();
    // }

    // public function createEvent(): JsonResponse
    // {
    // }

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

    // private function validateEvent(Event $event)
    // {
    // }

    // public function getPublishedEventsByCurrentUser(Event $event): JsonResponse
    // {

    // }

    // public function getDraftEventsByCurrentUser(Event $event): JsonResponse
    // {
    // }
    // public function updateEvent(Event $event): JsonResponse
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
