<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventInfo;
use App\Entity\Event\EventTask;
use App\Entity\Event\Section;
use App\Entity\Event\UserInfo;
use App\Entity\User\User;
use App\Repository\Event\EventRepository;
use App\Repository\Event\EventTaskRepository;
use App\Repository\Event\SectionRepository;
use App\Repository\Event\UserInfoRepository;
use App\Service\Event\TagService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\CurrentUser;
use App\Utils\JsonResponseBuilder;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\PersistentCollection;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Service class for handling event-related operations.
 * 
 * Methods:
 * - createOneEvent: Creates a new event in the database.
 * - updateEvent: Updates an existing event in the database.
 * - deleteEvent: Deletes an event from the database.
 * - getEventById: Retrieves an event by its ID.
 * - getEventsBySection: Retrieves events by section ID, type, and due date.
 * - getUserPendingEvents: Retrieves pending events created by the current user.
 * - getEventsCreatedByUser: Retrieves all events created by the current user.
 * - setTimestamps: Sets the timestamps for an event.
 * - setRelations: Sets the relations for an event.
 * - isVisibleForCurrentUser: Checks if an event is visible for the current user.
 * - getValidatedDataEventBySection: Validates and retrieves data for events by section.
 * - getEventsByCriteria: Retrieves events based on specified criteria.
 */
class EventService
{
    protected $now;
    protected $activeDayStart;
    protected $activeDayEnd;

    public function __construct(
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected ParameterBagInterface $parameterBag,
        protected TagService $tagService,
        protected UserInfoRepository $userInfoRepository,
        protected EventTaskRepository $eventTaskRepository,
        protected CurrentUser $currentUser,
        protected ValidatorService $validatorService,
        protected SectionRepository $sectionRepository,
        protected SerializerInterface $serializer,
        protected JsonResponseBuilder $jsonResponseBuilder
    ) {
        $this->now = new DateTimeImmutable('today');
        $this->activeDayStart = $this->parameterBag->get('activeDayStart');
        $this->activeDayEnd = $this->parameterBag->get('activeDayEnd');
    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Crée un nouvel événement dans la base de données.
     *
     * @param array $data Les données de l'événement à créer.
     * @return ApiResponse L'objet de réponse de succès ou d'erreur.
     */
    public function createOneEvent(array $data): ApiResponse
    {
        $currentUser = $this->currentUser->getCurrentUser();

        $event = $this->setEventBase($data, $currentUser);
        if ($event === null) {
            return ApiResponse::error('Error creating event: Invalid event data');
        }

        $this->setTimestamps($event);

        $users = $this->getEventUsers($currentUser, $data);
        // Si getEventUsers retourne une ApiResponse (erreur), on renvoie cette réponse
        if ($users instanceof ApiResponse) {
            return $users;
        }

        $this->setRelations($event, $users);

        $validator = $this->validatorService->validateEntity($event);
        if (!$validator->isSuccess()) {
            return $validator;
        }

        return $this->flushEvent($event);
    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Vérifie si un événement existe déjà dans la base de données.
     *
     * @param Event $event L'événement à vérifier.
     * @return bool True si l'événement existe déjà, sinon false.
     */
    private function doesEventAlreadyExist(Event $event): bool
    {
        $query = $this->em->createQuery(
            'SELECT COUNT(e.id)
             FROM App\Entity\Event\Event e
             WHERE e.title = :title
               AND e.dueDate = :dueDate
               AND e.section = :section
               AND e.type = :type
               AND e.side = :side
               AND e.isRecurring = :isRecurring'
        );

        $query->setParameters([
            'title'       => $event->getTitle(),
            'dueDate'     => $event->getDueDate()->format('Y-m-d'),
            'section'     => $event->getSection(),
            'type'        => $event->getType(),
            'side'        => $event->getSide(),
            'isRecurring' => $event->isRecurring(),
        ]);

        return (bool) $query->getSingleScalarResult();

    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Enregistre un événement dans la base de données.
     *
     * @param Event $event L'événement à enregistrer.
     * @return ApiResponse L'objet de réponse de succès ou d'erreur.
     */
    private function flushEvent(Event $event): ApiResponse
    {
        try {
            if ($this->doesEventAlreadyExist($event)) {
                return ApiResponse::error("Error creating event: Event already exists", null, Response::HTTP_CONFLICT);
            }
            try {
                $this->em->flush();
                return ApiResponse::success("Event created successfully", ["event" => $event], Response::HTTP_OK);
            } catch (ORMException $e) {
                return ApiResponse::error('Error saving event: ' . $e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (Exception $e) {
            return ApiResponse::error('Unexpected error: ' . $e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Récupère les utilisateurs associés à un événement.
     *
     * @param User $currentUser L'utilisateur connecté.
     * @param array $data Les données de l'événement.
     * @return ApiResponse|ArrayCollection La collection d'utilisateurs associés à l'événement.
     */
    private function getEventUsers(User $currentUser, array $data): ApiResponse|ArrayCollection
    {
        $users = [];
        $usersId = $data[ "usersId" ] ?? [];
        // Ajoute le current user uniquement s'il n'est pas déjà dans la liste
        if (!in_array($currentUser->getId(), $usersId)) {
            $usersId[] = $currentUser->getId();
        }
        foreach ($usersId as $userId) {

            $user = $this->em->getRepository(User::class)->find($userId);
            if (!$user) {
                return ApiResponse::error("Error creating event: User with id {$userId} not found", null, Response::HTTP_NOT_FOUND);
            }
            $users[] = $user;
        }

        return new ArrayCollection($users);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Met à jour un événement existant avec les données spécifiées.
     *
     * @param Event $event L'événement à mettre à jour.
     * @param array $data Les données de l'événement.
     * @return Event $event
     */
    private function setEventBase(array $data, User $user): Event
    {
        $section = $this->em->getRepository(Section::class)->findOneBy(["name" => $data[ "section" ]]);
        
        $dueDate = new DateTimeImmutable($data[ "dueDate" ]);
        $event = new Event();
        $event
            ->setDescription($data[ "description" ])
            ->setIsImportant($data[ "isImportant" ])
            ->setSide($data[ "side" ])
            ->setTitle($data[ "title" ])
            ->setCreatedBy($user->getFullName())
            ->setUpdatedBy($user->getFullName())
            ->setType($data[ "type" ])
            ->setSection($section)
            ->setDueDate($dueDate)
            ->setFirstDueDate($dueDate)
            ->setPublished($data[ "isPublished" ])
            ->setIsProcessed(false);

        // on verifie si l'event est published, sinon on passe isPending a true
        if (!$event->isPublished()) {
            $event->setPending(true);
        }

        $this->em->persist($event);
        return $event;

    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves the collection of users associated with a specific event.
     * 
     * This method determines the type of the event (`info` or other) and retrieves the corresponding
     * list of users who are associated with it. For `info` type events, it extracts users from
     * the `SharedWith` relationship of the `Info` entity. For other types (e.g., `task`), it fetches users
     * from the `SharedWith` relationship of the `Task` entity.
     * 
     * @param Event $event The event for which to retrieve the associated users.
     * 
     * @return Collection Returns a collection of users associated with the event.
     */
    public function getUsers(Event $event): Collection
    {
        if ($event->getType() === "info") {
            $usersinfo = $event->getInfo()->getSharedWith();
            $users = new ArrayCollection();
            foreach ($usersinfo as $userInfo) {
                $users->add($userInfo->getUser());
            }
        } else {
            $users = $event->getTask()->getSharedWith();
        }
        if ($users instanceof PersistentCollection && !$users->isInitialized()) {
            $users->initialize();
        }
        if (!$users) {
            throw new \InvalidArgumentException('Users not found');
        }
        return $users ?? new ArrayCollection();
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Définit les timestamps (date de statut et jour actif) pour un événement.
     *
     * @param Event $event L'événement pour lequel définir les timestamps.
     * @return void
     */
    public function setTimestamps(Event $event): void
    {
        $diff = (int) $this->now->diff($event->getDueDate())->format('%r%a');
        if ($diff >= $this->activeDayStart && $diff <= $this->activeDayEnd) {
            $dateStatus = "activeDayRange";
            $activeDay = $diff;
        } elseif ($diff >= -30 && $diff < $this->activeDayStart) {
            $dateStatus = "past";
            $activeDay = null;
        } else {
            $dateStatus = "future";
            $activeDay = null;
        }

        $event
            ->setActiveDay($activeDay)
            ->setDateStatus($dateStatus);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Définit les relations pour un événement, en fonction du type (task ou info).
     *
     * @param Event $event L'événement à modifier.
     * @param Collection $users Les utilisateurs associés à l'événement.
     */
    public function setRelations(Event $event, Collection $users, string $taskStatus = "todo"): void
    {
        $type = $event->getType();
        $type === "task" ?
            $this->setTask($event, $taskStatus, $users) :
            $this->setInfo($event, $users);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Définit la tâche associée à un événement.
     *
     * @param Event $event L'événement auquel associer une tâche.
     * @param string $taskStatus Le statut de la tâche.
     * @param Collection $users Les utilisateurs associés à la tâche.
     * @return void
     */
    public function setTask(Event $event, string $taskStatus, Collection $users): void
    {
        // on verifie que l'event est published, sinon on passe le status a pending
        if (!$event->isPublished()) {
            $taskStatus = "pending";
        }
        $task = (new EventTask())
            ->setTaskStatus($taskStatus)
            ->setSharedWithCount($users->count());
        foreach ($users as $user) {
            $task->addSharedWith($user);
        }
        $task->setEvent($event);
        $this->em->persist($task);

        $event->setTask($task);
        $event->setPending($taskStatus === "pending");
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Définit les informations associées à un événement.
     *
     * @param Event $event L'événement auquel associer des informations.
     * @param Collection $users Les utilisateurs auxquels partager les informations.
     * @return EventInfo L'objet EventInfo créé.
     */
    public function setInfo(Event $event, Collection $users): EventInfo
    {
        $info = (new EventInfo())
            ->setIsFullyRead(false)
            ->setUserReadInfoCount(0)
            ->setSharedWithCount($users->count())
            ->setOld(false);

        $this->em->persist($info);
        $event->setInfo($info);
        $this->setSharedInfo($users, $info);

        return $info;
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Définit les informations partagées avec les utilisateurs.
     *
     * @param Collection $users Les utilisateurs avec lesquels partager l'information.
     * @param EventInfo $info L'information à partager.
     */
    public function setSharedInfo(Collection $users, EventInfo $info): void
    {
        foreach ($users as $user) {
            $sharedInfo = (new UserInfo())
                ->setUser($user)
                ->setEventInfo($info)
                ->setIsRead(false);

            $this->em->persist($sharedInfo);
            $info->addSharedWith($sharedInfo);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Marks an info event as read for a specific user.
     * 
     * This method updates the `isRead` status of a `UserInfo` entity to `true` for the given user and
     * the corresponding `Info` entity of the event. Additionally, it synchronizes the counts of the 
     * associated `Info` entity.
     * 
     * @param Event $event The event whose info is marked as read.
     * @param User $user The user who has read the info.
     * 
     * @return void
     * 
     * @throws Exception If the corresponding UserInfo entity is not found.
     */
    public function infoIsRead(Event $event, User $user): void
    {
        $userInfo = $this->userInfoRepository->findOneBy(["user" => $user, "eventInfo" => $event->getInfo()]);
        $userInfo->setIsRead(true);
        $event->getInfo()->syncCounts();
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Removes an event and updates the tag counters for associated users.
     *
     * This method deletes the provided event entity from the database and adjusts
     * the associated tag counters for a given list of users. 
     * @param Event $event The event to be deleted.
     * @return ApiResponse A ApiResponse object indicating success or failure of the operation.
     *
     * @throws ORMException If a database error occurs during the event removal.
     * @throws Exception For any unexpected errors during processing.
     */
    public function removeEventAndUpdateTagCounters(Event $event): ApiResponse
    {
        try {
            $this->em->remove($event);
            $response = $this->tagService->decrementSharedUsersTagCountByOne($event);
            $this->em->flush();
            return ApiResponse::success('Event deleted successfully and ' . $response->getMessage());
        } catch (ORMException $e) {
            return ApiResponse::error('An error occurred while deleting the event: ' . $e->getMessage());
        } catch (Exception $e) {
            return ApiResponse::error('An unexpected error occurred: ' . $e->getMessage());
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Removes the user from all EventInfos they are associated with.
     * 
     * This method updates the UserInfo entities, removes the user from the 
     * sharedWith collection of EventInfo, and deletes the related EventInfo 
     * if no other users are associated with it.
     * @param User $user The user to be removed from all EventInfos.
     * @return void
     */
    public function removeUserFromAllEventInfos(User $user): ApiResponse
    {
        try {
            // Récupérer tous les UserInfo liés à l'utilisateur
            /** @var UserInfo[] $userInfos */
            $userInfos = $this->userInfoRepository->findBy(['user' => $user]);

            // Pour chaque UserInfo, on met à jour l'EventInfo et l'utilisateur
            foreach ($userInfos as $userInfo) {
                $eventInfo = $userInfo->getEventInfo();
                // Retire le UserInfo de la collection sharedWith de l'EventInfo. 
                // La méthode removeSharedWith appelle syncCounts pour mettre à jour les compteurs.
                $eventInfo->removeSharedWith($userInfo);
                // Suppression explicite du UserInfo, car orphanRemoval n'est plus activé
                $this->em->remove($userInfo);
                // Si aucune autre personne n'est associée à cet EventInfo, le supprimer
                if ($eventInfo->getSharedWithCount() === 0) {
                    $event = $eventInfo->getEvent();
                    if ($event !== null) {
                        $this->em->remove($event);
                    }
                }
            }
            $this->em->flush();
            return ApiResponse::success('User removed from all EventInfos successfully');
        } catch (ORMException $e) {
            return ApiResponse::error('An error occurred while removing the user from EventInfos: ' . $e->getMessage());
        } catch (Exception $e) {
            return ApiResponse::error('An unexpected error occurred while removing the user: ' . $e->getMessage());
        }
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Removes the user from all EventTasks they are associated with.
     * 
     * This method removes the user from the sharedWith collection of each EventTask,
     * updates the sharedWithCount, and deletes the EventTask if no other users 
     * are associated with it. If the EventTask is associated with an Event, 
     * the Event will also be removed if no tasks are associated.
     *
     * @param User $user The user to be removed from all EventTasks.
     * 
     * @return void
     */
    public function removeUserFromAllEventTasks(User $user): ApiResponse
    {
        try {
            // Récupérer toutes les tâches associées à cet utilisateur

            $eventTasks = $this->eventTaskRepository->findByUserInSharedWith($user);

            foreach ($eventTasks as $eventTask) {
                // Retirer l'utilisateur de la collection sharedWith
                $eventTask->removeSharedWith($user);

                // Mettre à jour le compteur partagé
                $eventTask->setSharedWithCount($eventTask->getSharedWith()->count());

                // Si aucune autre personne n'est associée à cette tâche, supprimer complètement la tâche et son event lié
                if ($eventTask->getSharedWithCount() === 0) {
                    $this->em->remove($eventTask);
                }
            }
            $this->em->flush();
            return ApiResponse::success('User removed from all EventTasks successfully');
        } catch (ORMException $e) {
            return ApiResponse::error('An error occurred while removing the user from EventTasks: ' . $e->getMessage());
        } catch (Exception $e) {
            return ApiResponse::error('An unexpected error occurred while removing the user: ' . $e->getMessage());
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Removes the user from all EventInfos and EventTasks they are associated with.
     * 
     * This method calls both `removeUserFromAllEventInfos()` and 
     * `removeUserFromAllEventTasks()` to ensure that the user is removed from 
     * all Event-related data.
     *
     * @param User $user The user to be removed from all Events.
     * 
     * @return void
     */
    public function removeUserFromAllEvents(User $user): ApiResponse
    {
        try {
            $this->removeUserFromAllEventInfos($user);
            $this->removeUserFromAllEventTasks($user);
            return ApiResponse::success('User removed from all events successfully');
        } catch (Exception $e) {
            return ApiResponse::error('An unexpected error occurred while removing the user from all events: ' . $e->getMessage());
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Filters events from the database based on one or more criteria: dueDate, side, section, or status.
     * 
     * This method directly queries the database using the provided parameters. If no criteria
     * are specified, all events are returned. Returns a structured response indicating
     * success or failure.
     *
     * **Warning:** Since this method queries the database, frequent use with complex filters
     * may impact performance. Consider caching results for recurring queries if necessary.
     *
     * @param DateTimeImmutable|null $dueDate The due date to filter by, or null to ignore.
     * @param string|null $side The side to filter by, or null to ignore.
     * @param string|null $section The section to filter by, or null to ignore.
     * @param string|null $status The task status to filter by, or null to ignore.
     * 
     * @return ApiResponse A ApiResponse object containing the filtered events or an error message.
     */
    public function filterEvents(
        ?DateTimeImmutable $dueDate,
        ?string $side,
        ?string $section,
        ?string $status
    ): ApiResponse {
        try {
            // Create query builder to build the query dynamically
            $qb = $this->eventRepository->createQueryBuilder('e');

            if ($dueDate) {
                $qb->andWhere('e.dueDate = :dueDate')->setParameter('dueDate', $dueDate);
            }
            if ($side) {
                $qb->andWhere('e.side = :side')->setParameter('side', $side);
            }
            if ($section) {
                $qb->andWhere('e.section = :section')->setParameter('section', $section);
            }
            if ($status) {
                $qb->join('e.task', 't')
                    ->andWhere('t.taskStatus = :status')
                    ->setParameter('status', $status);
            }

            // Execute the query to fetch results
            $filteredEvents = $qb->getQuery()->getResult();

            return ApiResponse::success('Events filtered successfully.', $filteredEvents);
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while filtering events: ' . $e->getMessage());
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Vérifie si l'événement est partagé avec l'utilisateur connecté.
     *
     * Cette méthode vérifie si l'événement est associé à l'utilisateur courant. 
     *
     * @param Event $event L'événement pour lequel on vérifie les utilisateurs associés.
     *
     * @return bool True si l'événement est partagé avec l'utilisateur courant, sinon false.
     */
    protected function isSharedWithUser(Event $event): bool
    {
        $user = $this->currentUser->getCurrentUser();
        return $this->getUsers($event)->contains($user);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Vérifie si l'événement est visible pour l'utilisateur connecté.
     *
     * Cette méthode vérifie si l'événement est partagé avec l'utilisateur connecté et si l'événement n'est pas encore publié, que l'auteur est l'utilisateur courant.
     *
     * @param Event $event L'événement à vérifier.
     *
     * @return bool True si l'événement est visible pour l'utilisateur courant, sinon false.
     */
    public function isVisibleForCurrentUser(Event $event): bool
    {
        // verify that the event is shared with the user and if the event is not yet published that the author is the current user
        $isPublishedByCurrentUser =
            !$event->isPublished() && $event->getCreatedBy() !== $this->currentUser->getCurrentUser()->getFullName();

        return $this->isSharedWithUser($event) && $isPublishedByCurrentUser;
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Récupère une liste d'événements en fonction de critères donnés.
     *
     * @param array $criteria Tableau associatif de critères pour filtrer les événements.
     *                        - Les clés correspondent aux champs de l'entité `Event`.
     *                        - Les valeurs peuvent être des valeurs simples (égalité) ou des tableaux (inclusion dans une liste).
     *
     * @return JsonResponse Réponse JSON contenant les événements correspondant aux critères ou un message d'erreur.
     *
     * @throws \Exception Peut lever une exception en cas d'erreur avec la requête ou la sérialisation des données.
     *
     * Exemple d'utilisation :
     * ```php
     * $criteria = ['createdBy' => 'John Doe', 'isPending' => true];
     * $response = $this->getEventsByCriteria($criteria);
     * ```
     */
    public function getEventsByCriteria(array $criteria): JsonResponse
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('e')
            ->from(Event::class, 'e');

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $qb->andWhere($qb->expr()->in("e.$field", ":$field"))
                    ->setParameter($field, $value);
            } else {
                $qb->andWhere("e.$field = :$field")
                    ->setParameter($field, $value);
            }
        }

        $events = $qb->getQuery()->getResult();

        $response = ApiResponse::success("Events retrieved successfully", ['events' => $events], Response::HTTP_OK);
        if ($response->isSuccess()) {
            return $this->jsonResponseBuilder->createJsonResponse([$response->getMessage(), $response->getData()], $response->getStatusCode(), $response->isSuccess() ? ["eventIds"] : []);
        } else {
            return $this->jsonResponseBuilder->createJsonResponse($response->getMessage(), $response->getStatusCode());
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
    public function getValidatedDataEventBySection(int $sectionId, Request $request): array|JsonResponse
    {
        // Vérification de l'existence de la section
        $section = $this->sectionRepository->find($sectionId);
        if (!$section) {
            return new JsonResponse(ApiResponse::error("Section not found", null, Response::HTTP_NOT_FOUND));
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
            return new JsonResponse($response, $response->getStatusCode());
        }

        // Extraction des données validées
        $type = $response->getData()[ 'type' ];
        $dueDate = new DateTimeImmutable($response->getData()[ 'dueDate' ]);
        $userId = $this->currentUser->getCurrentUser()->getId();

        return [$type, $dueDate, $userId];
    }
    //! --------------------------------------------------------------------------------------------

    public function getValidatedDataEventCreation(int $sectionId, Request $request): array|JsonResponse
    {
        // Vérification de l'existence de la section
        $section = $this->sectionRepository->find($sectionId);
        if (!$section) {
            return new JsonResponse(ApiResponse::error("Section not found", null, Response::HTTP_NOT_FOUND));
        }

        // Définition des contraintes pour la requête JSON
        $constraints = new Assert\Collection([
            'dueDate' => [
                new Assert\NotBlank(message: "Due date is required."),
                new Assert\Date(message: "Invalid date format. Expected format: 'Y-m-d'")
            ]
        ]);

        // Validation des données
        $response = $this->validatorService->validateJson($request, $constraints);
        if (!$response->isSuccess()) {
            return new JsonResponse($response, $response->getStatusCode());
        }

        // Extraction des données validées
        $type = $response->getData()[ 'type' ];
        $dueDate = new DateTimeImmutable($response->getData()[ 'dueDate' ]);
        $userId = $this->currentUser->getCurrentUser()->getId();

        return [$type, $dueDate, $userId];
    }

}