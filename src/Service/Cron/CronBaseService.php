<?php

namespace App\Service\Cron;

use App\Entity\Event\Event;
use App\Entity\Event\EventInfo;
use App\Entity\Event\EventRecurring;
use App\Entity\Event\EventSharedInfo;
use App\Entity\Event\EventTask;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use App\Service\Event\EventRecurringService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CronBaseService
{
    protected $now;
    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventRecurringService $eventRecurringService,
        protected ParameterBagInterface $parameterBag
    ) {
        $now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
    }

    public function getEventRecurringParents(): array
    {
        $eventRecurringParents = $this->eventRecurringRepository->findAll();
        return $eventRecurringParents;
    }

    public function getEventRecurringChildrens(): array
    {
        $eventRecurringChildrens = $this->eventRepository->findBy(["isRecurring" => "true"]);
        return $eventRecurringChildrens;
    }

    

    public function handleMonthDays(EventRecurring $eventRecurring): void
    {

    }
    public function handleWeekdays(EventRecurring $eventRecurring): void
    {

    }
    public function handlePeriodDates(EventRecurring $eventRecurring): void
    {

    }
    public function handleEveryday(EventRecurring $eventRecurring): void
    {
        $this->createEverydayChildren($eventRecurring);
    }

    public function createEverydayChildren(EventRecurring $eventRecurring): void
    {
        $event = $this->setRecurringEventBase($eventRecurring);
        $this->setRelations($event, "todo");
        $this->em->flush();
    }


    public function setRecurringEventBase(EventRecurring $eventRecurring): Event
    {
        $event = (new Event())
            ->setDescription($eventRecurring->getDescription())
            ->setIsImportant(false)
            ->setSide($eventRecurring->getSide())
            ->setTitle($eventRecurring->getTitle())
            ->setCreatedBy($eventRecurring->getCreatedBy()->getFullName())
            ->setUpdatedBy($eventRecurring->getUpdatedBy()->getFullName())
            ->setType($eventRecurring->getType())
            ->setSection($eventRecurring->getSection())
            ->setIsRecurring(true)
            ->setActiveDay(0)
            ->setDueDate($this->now)
            ->setDateStatus("activeDayRange")
            ->setCreatedAt($this->now)
            ->setUpdatedAt($this->now);

        $eventRecurring->addEvent($event);

        $this->em->persist($event);
        return $event;
    }
    public function setRelations(Event $event, string $status = null, int $count = null): void
    {
        $type = $event->getType();
        if ($type === "task") {
            $this->setTask($event, $status);
        } elseif ($type === "info") {
            $this->setInfo($event, $count);
        }
    }
    public function setTask(Event $event, string $taskStatus): void
    {
        $task = (new EventTask())
            ->setTaskStatus($taskStatus)
            ->setCreatedAt($this->now)
            ->setUpdatedAt($this->now);
        $this->em->persist($task);

        $event->setTask($task);
    }
    public function setInfo(Event $event, int $count): EventInfo
    {
        $info = (new EventInfo())
            ->setCreatedAt($this->now)
            ->setUpdatedAt($this->now)
            ->setIsFullyRead(false)
            ->setUserReadInfoCount(0);

        $count = $this->setEventSharedInfo($event->getEventRecurring(), $info);
        $info->setSharedWithCount($count);

        $this->em->persist($info);
        $event->setInfo($info);

        return $info;
    }

    public function setEventSharedInfo(EventRecurring $eventRecurring, EventInfo $info): int
    {
        $users = $eventRecurring->getSharedWith();
        $count = count($users);
        foreach ($users as $user) {
            $eventSharedInfo = (new EventSharedInfo())
                ->setUser($user)
                ->setEventInfo($info)
                ->setIsRead(false)
                ->setCreatedAt($this->now)
                ->setUpdatedAt($this->now);
            $this->em->persist($eventSharedInfo);
            $info->addEventSharedInfo($eventSharedInfo);
        }
        return $count;
    }
}