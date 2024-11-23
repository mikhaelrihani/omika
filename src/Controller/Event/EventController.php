<?php

namespace App\Controller\Event;

use App\Entity\Event\Event;
use App\Repository\Event\EventRepository;
use App\Service\Event\EventService;
use App\Utils\ApiResponse;
use App\Utils\CurrentUser;
use App\Utils\EventUsers;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * @param EventUsers $eventUsers Service utilitaire pour récupérer les utilisateurs associés à un événement.
     */
    public function __construct(
        private EventService $eventService,
        private EventRepository $eventRepository,
        private CurrentUser $currentUser,
        private EventUsers $eventUsers
    ) {
    }

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

    /**
     * Récupère un événement par son identifiant.
     *
     * @param int $id L'identifiant de l'événement.
     *
     * @return JsonResponse La réponse contenant l'événement ou un message d'erreur.
     *
     * @throws \LogicException Si la méthode est appelée dans un contexte invalide.
     * @throws \InvalidArgumentException Si les paramètres passés ne permettent pas de trouver un résultat valide.
     */
    #[Route('/{id}', name: 'getEvent', methods: ['GET'])]
    public function getEvent(int $id): JsonResponse
    {
      
        $event = $this->eventRepository->find($id);

        if (!$event) {
            $response = ApiResponse::error(
                "There is no event with this id",
                null,
                Response::HTTP_NOT_FOUND
            );
            return $this->json($response);
        }

        // Vérifie si l'utilisateur a les droits d'accès
        $isAllowed = $this->isAllowed($event);
        if (!$isAllowed) {
            $response = ApiResponse::error(
                "You are not allowed to see this event",
                null,
                Response::HTTP_FORBIDDEN
            );
            return $this->json($response);
        }

        $response = ApiResponse::success(
            "Event retrieved successfully",
            ['event' => $event]
        );
        return $this->json($response, 200, [], ['groups' => 'event']);
    }


    public function createEvent(): JsonResponse
    {
    }
    /**
     * Vérifie si l'événement est partagé avec l'utilisateur connecté.
     *
     * Cette méthode vérifie si l'événement est associé à l'utilisateur courant. Elle fait appel au service
     * `EventUsers` pour récupérer les utilisateurs associés à l'événement et vérifie si l'utilisateur
     * connecté en fait partie.
     *
     * @param Event $event L'événement pour lequel on vérifie les utilisateurs associés.
     *
     * @return bool True si l'événement est partagé avec l'utilisateur courant, sinon false.
     */
    private function isSharedWithUser(Event $event): bool
    {
        $user = $this->currentUser->getCurrentUser();
        $users = $this->eventUsers->getUsers($event);

        $isSharedWithUser = $users->contains($user);

        return $isSharedWithUser;
    }

    /**
     * Vérifie si l'utilisateur a les droits d'accès à un événement.
     *
     * Cette méthode vérifie deux conditions : 
     * - L'utilisateur doit être activé (`isEnabled()`).
     * - L'événement doit être partagé avec l'utilisateur.
     *
     * @param Event $event L'événement pour lequel on vérifie les droits d'accès.
     *
     * @return bool True si l'utilisateur a les droits d'accès, sinon false.
     */
    private function isAllowed(Event $event): bool
    {
        return $this->getUser()->isEnabled() && $this->isSharedWithUser($event);
    }

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
