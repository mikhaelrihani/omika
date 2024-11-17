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

    public function deleteEvent($event, $user)
    {
        $this->em->remove($event);//! verifier l'effacement des relations
        $response = $this->tagService->decrementTagCounterByOne($event, $user, false);
        $this->em->flush();
        return ResponseService::success('Event deleted successfully and ' . $response->getMessage());
    }

    public function removeUserFromAllEventInfos(User $user): void
    {
        // Récupérer tous les UserInfo liés à l'utilisateur
        $userInfos = $this->userInfoRepository->findBy(['user' => $user]);

        // Pour chaque UserInfo, on met à jour l'EventInfo et l'utilisateur
        foreach ($userInfos as $userInfo) {
            $eventInfo = $userInfo->getEventInfo();

            // Si l'utilisateur a marqué l'info comme lue, on met à jour le compteur des utilisateurs ayant lu l'info
            if ($userInfo->isRead()) {
                $eventInfo->setUserReadInfoCount($eventInfo->getUserReadInfoCount() - 1);
            }
            // Retire le UserInfo de la collection sharedWith de l'EventInfo. 
            // La méthode removeSharedWith appelle syncCounts pour mettre à jour les compteurs.
            $eventInfo->removeSharedWith($userInfo);
            // Suppression explicite du UserInfo, car orphanRemoval n'est plus activé
            $this->em->remove($userInfo);
            // Si aucune autre personne n'est associée à cet EventInfo, le supprimer
            if ($eventInfo->getSharedWithCount() === 0) {
                $this->em->remove($eventInfo);
                // Si un Event est lié à cet EventInfo, le supprimer aussi
                $event = $eventInfo->getEvent();
                if ($event !== null) {
                    $this->em->remove($event);
                }
            }
        }
        $this->em->flush();
    }

    public function removeUserFromAllEventTasks(User $user): void
    {
        // Récupérer toutes les tâches associées à cet utilisateur
        $eventTasks = $this->eventTaskRepository->findByUserInSharedWith($user);

        foreach ($eventTasks as $eventTask) {
            // Retirer l'utilisateur de la collection sharedWith
            $eventTask->removeSharedWith($user);

            // Mettre à jour le compteur partagé
            $eventTask->setSharedWithCount($eventTask->getSharedWith()->count());

            // Si aucune autre personne n'est associée à cette tâche, supprimer complètement la tâche
            if ($eventTask->getSharedWithCount() === 0) {
                $this->em->remove($eventTask);
            }
        }

        // Enregistrer toutes les modifications dans la base de données
        $this->em->flush();
    }

}
