<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\User\User;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use App\Service\TagService;
use DateTimeImmutable;
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
        $this->handleYesterdayEvents();
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

    public function handleYesterdayEvents()
    {
        $yesterdayEvents = $this->findYesterdayEvents();
        // on check si la tache a été réalisée, ou si l'info a été lue
        foreach ($yesterdayEvents as $yesterdayEvent) {
            $users = $this->eventService->getUsers($yesterdayEvent);
            if ($yesterdayEvent->getTask) {
                $taskStatus = $yesterdayEvent->getTask()->getStatus();
                if ($taskStatus === 'done') {
                    continue;
                } else {
                    $yesterdayEvent->getTask()->setStatus('unrealised');
                    $this->handleOneYesterdayEvent($yesterdayEvent, $users, $taskStatus);
                }
            } else if ($yesterdayEvent->getInfo) {
                $this->handleOneYesterdayEvent($yesterdayEvent, $users);

                // $userInfos = $yesterdayEvent->getInfo()->getSharedWith();
                // foreach ($userInfos as $userInfo) {
                //     if ($userInfo->getIsRead() === true) {
                //         continue;
                //     } else {
                //         $this->handleOneYesterdayEvent($yesterdayEvent, $users);
                //     }
                // }
            }
        }
    }

    public function handleOneYesterdayEvent(Event $yesterdayEvent, Collection $users, string $taskStatus = null)
    {
        $todayEvent = $this->duplicateEventBase($yesterdayEvent);

        $this->eventService->setRelations($todayEvent, $users);
        if ($todayEvent->getTask()) {
            $todayEvent->getTask()->setTaskStatus("late");
        }
        $this->eventService->setTimestamps($todayEvent);
        $this->em->flush();

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