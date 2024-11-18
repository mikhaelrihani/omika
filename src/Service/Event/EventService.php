<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventInfo;
use App\Entity\Event\EventTask;
use App\Entity\Event\UserInfo;
use App\Entity\User\User;
use App\Repository\Event\EventRepository;
use App\Repository\Event\EventTaskRepository;
use App\Repository\Event\UserInfoRepository;
use App\Service\ResponseService;
use App\Service\TagService;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

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
        protected EventTaskRepository $eventTaskRepository

    ) {
        $this->now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
        $this->activeDayStart = $this->parameterBag->get('active_day_start');
        $this->activeDayEnd = $this->parameterBag->get('active_day_end');
    }

    /**
     * Crée un événement en utilisant les données spécifiées.
     *
     * @param array $data Les données de l'événement.
     * @return ResponseService L'objet de réponse de succès ou d'erreur.
     */
    public function createOneEvent(array $data): ResponseService
    {
        $event = $this->setEventBase($data);
        if ($event === null) {
            return ResponseService::error('Error creating event: Invalid event data');
        }

        $this->setTimestamps($event);
        $this->setRelations($event, $data[ "status" ], $data[ "users" ]);

        return ResponseService::success('Event created successfully', ['event' => $event]);
    }

    /**
     * Définit les propriétés de base de l'événement.
     *
     * @param array $data Les données pour initialiser l'événement.
     * @return Event|null L'événement nouvellement créé ou null si une erreur se produit.
     */
    public function setEventBase(array $data): Event|ResponseService
    {
        try {
            $event = new Event();
            $event
                ->setDescription($data[ "description" ])
                ->setIsImportant($data[ "isImportant" ])
                ->setSide($data[ "side" ])
                ->setTitle($data[ "title" ])
                ->setCreatedBy($data[ "createdBy" ])
                ->setUpdatedBy($data[ "updatedBy" ])
                ->setType($data[ "type" ])
                ->setSection($data[ "section" ])
                ->setDueDate($data[ "dueDate" ]);

            return $event;
        } catch (\Exception $e) {
            // Retourne une erreur avec un message précis
            return ResponseService::error('Error setting event base properties: ' . $e->getMessage());
        }
    }

    /**
     * Définit les relations pour un événement, en fonction du type (task ou info).
     *
     * @param Event $event L'événement à modifier.
     * @param string|null $status Le statut de la tâche, s'il s'agit d'une tâche.
     * @param Collection $users Les utilisateurs associés à l'événement.
     */
    public function setRelations(Event $event, string $status = null, Collection $users): void
    {
        $type = $event->getType();
        if ($type === "task") {
            $this->setTask($event, $status, $users);
        } elseif ($type === "info") {
            $this->setInfo($event, $users);
        }
    }

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
        $count = count($users);
        $task = (new EventTask())
            ->setTaskStatus($taskStatus)
            ->setSharedWithCount($count);

        $this->em->persist($task);
        foreach ($users as $user) {
            $task->addSharedWith($user);
        }
        $event->setTask($task);
    }

    /**
     * Définit les informations associées à un événement.
     *
     * @param Event $event L'événement auquel associer des informations.
     * @param Collection $users Les utilisateurs auxquels partager les informations.
     * @return EventInfo L'objet EventInfo créé.
     */
    public function setInfo(Event $event, Collection $users): EventInfo
    {
        $count = count($users);
        $info = (new EventInfo())
            ->setIsFullyRead(false)
            ->setUserReadInfoCount(0)
            ->setSharedWithCount($count);

        $this->em->persist($info);
        $event->setInfo($info);
        $this->setSharedInfo($users, $info);

        return $info;
    }

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

    /**
     * Deletes an event and decrements its related tag counters.
     * 
     * This method removes the event from the database and decrements the counters
     * of tags associated with the event.
     *
     * @param Event $event The event to be deleted.
     * @param User $user The user requesting the deletion (used for tag counter adjustment).
     * 
     * @return ResponseService The response message indicating success or failure.
     */
    public function removeEventAndUpdateTagCounters($event, $user)
    {
        try {
            $this->em->remove($event);
            $response = $this->tagService->decrementTagCounterByOne($event, $user, false);
            $this->em->flush();
            return ResponseService::success('Event deleted successfully and ' . $response->getMessage());
        } catch (ORMException $e) {
            return ResponseService::error('An error occurred while deleting the event: ' . $e->getMessage());
        } catch (\Exception $e) {
            return ResponseService::error('An unexpected error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Removes the user from all EventInfos they are associated with.
     * 
     * This method updates the UserInfo entities, removes the user from the 
     * sharedWith collection of EventInfo, and deletes the related EventInfo 
     * if no other users are associated with it.
     * @param User $user The user to be removed from all EventInfos.
     * @return void
     */
    public function removeUserFromAllEventInfos(User $user): ResponseService
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
            return ResponseService::success('User removed from all EventInfos successfully');
        } catch (ORMException $e) {
            return ResponseService::error('An error occurred while removing the user from EventInfos: ' . $e->getMessage());
        } catch (\Exception $e) {
            return ResponseService::error('An unexpected error occurred while removing the user: ' . $e->getMessage());
        }
    }

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
    public function removeUserFromAllEventTasks(User $user): ResponseService
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
            return ResponseService::success('User removed from all EventTasks successfully');
        } catch (ORMException $e) {
            return ResponseService::error('An error occurred while removing the user from EventTasks: ' . $e->getMessage());
        } catch (\Exception $e) {
            return ResponseService::error('An unexpected error occurred while removing the user: ' . $e->getMessage());
        }
    }

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
    public function removeUserFromAllEvents(User $user): ResponseService
    {
        try {
            $this->removeUserFromAllEventInfos($user);
            $this->removeUserFromAllEventTasks($user);
            return ResponseService::success('User removed from all events successfully');
        } catch (\Exception $e) {
            return ResponseService::error('An unexpected error occurred while removing the user from all events: ' . $e->getMessage());
        }
    }


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
     * @return ResponseService A ResponseService object containing the filtered events or an error message.
     */
    public function filterEvents(
        ?DateTimeImmutable $dueDate,
        ?string $side,
        ?string $section,
        ?string $status
    ): ResponseService {
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

            return ResponseService::success('Events filtered successfully.', $filteredEvents);
        } catch (\Exception $e) {
            return ResponseService::error('An error occurred while filtering events: ' . $e->getMessage());
        }
    }

   

   

   
    public function updateEventStatusAfterRecurringEventUpdate($eventRecurring)
    {

    }
}
