<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\User\User;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use App\Service\TagService;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CronService
{
    protected $now;
    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventRecurringService $eventRecurringService,
        protected ParameterBagInterface $parameterBag,
        protected TagService $tagService,
        protected EventService $eventService
    ) {
        $now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
    }
    private function load()
    {
        $todayEvent = $this->handleYesterdayEvents();
        $this->tagService->createTag($todayEvent);
        $this->tagService->deletePastTag();
    }
    // public function getEventRecurringParents(): array
    // {
    //     $eventRecurringParents = $this->eventRecurringRepository->findAll();
    //     return $eventRecurringParents;
    // }

    // public function getEventRecurringChildrens(): array
    // {
    //     $eventRecurringChildrens = $this->eventRepository->findBy(["isRecurring" => "true"]);
    //     return $eventRecurringChildrens;
    // }

    public function findYesterdayEvents()
    {
        $dueDate = $this->now->modify('-1 day');
        $query = $this->em->createQuery('SELECT e FROM App\Entity\Event\Event e WHERE e.dueDate = :dueDate');
        $yesterdayEvents = $query->setParameter('dueDate', $dueDate)->getResult();
        return $yesterdayEvents;
    }

    public function handleYesterdayEvents(): Event|null
    {
        $yesterdayEvents = $this->findYesterdayEvents();
        // Vérification si la tâche a été réalisée ou si l'information a été lue
        // - Si la tâche n'est pas réalisée, on passe son statut à "unrealised" pour l'événement d'hier.
        //   Ensuite, on duplique la tâche associée à l'événement pour aujourd'hui et on l'attribue aux mêmes utilisateurs.
        // - Si la tâche est encore "pending", on change son statut à "unrealised" pour l'événement d'hier.
        //   On met également le champ "isPending" à true sur la tâche du nouvel événement (aujourd'hui),
        //   ce qui permet d'indiquer à l'utilisateur que la tâche est toujours en cours.
        //   La valeur de "firstDueDate" est conservée pour indiquer la date depuis laquelle la tâche est en attente.
        // - Pour les informations, on conserve l'info de l'événement d'hier intacte.
        //   On duplique l'info pour l'événement d'aujourd'hui, mais uniquement pour les utilisateurs qui ne l'ont pas encore lue.
        //   On passe le champ "isOld" à true pour les anciennes informations, ce qui permet de les classer séparément des nouvelles.
        //   La valeur de "firstDueDate" est également copiée dans l'info de l'événement d'aujourd'hui pour indiquer depuis quand elle est ancienne.

        foreach ($yesterdayEvents as $yesterdayEvent) {
            $users = $this->eventService->getUsers($yesterdayEvent);
            if ($yesterdayEvent->getTask()) {
                $taskStatus = $yesterdayEvent->getTask()->getStatus();
                if ($taskStatus === 'done') {
                    continue;
                } else {
                    $yesterdayEvent->getTask()->setTaskStatus('unrealised');
                    return $this->createTodayEvent($yesterdayEvent, $users, $taskStatus);
                }
            } else if ($yesterdayEvent->getInfo) {
                $isFullyRead = $yesterdayEvent->getInfo()->getIsFullyRead();
                if ($isFullyRead) {
                    continue;
                } else {
                    $userInfos = $yesterdayEvent->getInfo()->getSharedWith();
                    $users = new ArrayCollection();
                    foreach ($userInfos as $userInfo) {
                        if ($userInfo->getIsRead() === true) {
                            continue;
                        } else {
                            $users->add($userInfo->getUser());
                        }
                    }
                }
                return $this->createTodayEvent($yesterdayEvent, $users);

            } else {
                return null;
            }
        }
        return null;
    }
    public function createTodayEvent(Event $yesterdayEvent, Collection $users, string $taskStatus = null): Event
    {
        $todayEvent = $yesterdayEvent->isRecurring() ?
            $this->createRecuringTodayEvent($yesterdayEvent, $users) :
            $this->createNonRecuringTodayEvent($yesterdayEvent, $users);
        return $todayEvent;
    }
    public function createNonRecuringTodayEvent(Event $yesterdayEvent, Collection $users, string $taskStatus = null): Event
    {
        // il faut vérifier si l'event est recurrent
        $todayEvent = $this->duplicateEventBase($yesterdayEvent);
        $this->eventService->setRelations($todayEvent, $users, "late");

        if ($todayEvent->getTask()) {
            if ($taskStatus === 'pending' || $yesterdayEvent->getTask()->isPending()) {
                $yesterdayEvent->getTask()->setPending(true);
                $todayEvent->getTask()->setPending(true);
            }
        } else {
            $todayEvent->getInfo()->setOld(true);
        }
        $todayEvent->setDueDate($this->now);
        $todayEvent->setFirstDueDate($yesterdayEvent->getFirstDueDate());
        $this->eventService->setTimestamps($todayEvent);
        $todayEvent->setIsRecurring(false);//! penser a passer a true si recurring
        $this->em->flush();
        return $todayEvent;
    }

    public function createRecuringTodayEvent(Event $yesterdayEvent, Collection $users): Event
    {
        $todayEvent->setIsRecurring(true);
        return $todayEvent;
    }
    public function duplicateEventBase(Event $originalEvent): Event
    {
        $event = (new Event())
            ->setSide($originalEvent->getSide())
            ->setType($originalEvent->getType())
            ->setTitle($originalEvent->getTitle())
            ->setDescription($originalEvent->getDescription())
            ->setCreatedBy($originalEvent->getCreatedBy())
            ->setUpdatedBy($originalEvent->getUpdatedBy())
            ->setIsImportant($originalEvent->isImportant())
            ->setSection($originalEvent->getSection());
        return $event;
    }

    public function updateTagCount(Event $event)
    {

    }
}