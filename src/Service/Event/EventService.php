<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventInfo;
use App\Entity\Event\EventSharedInfo;
use App\Entity\Event\EventTask;
use App\Repository\Event\EventRepository;
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
        protected ParameterBagInterface $parameterBag
    ) {
        $now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
        $activeDayStart = $this->parameterBag->get('active_day_start');
        $activeDayEnd = $this->parameterBag->get('active_day_end');
    }

    public function createOneEvent(array $data): Event
    {

        $event = $this->setEventBase($data);
        $this->setTimestamps($event);
        $this->setRelations($event, $data[ "status" ], $data[ "users" ]);
        return $event;
    }

    public function setEventBase(array $data): Event
    {
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
    }

    public function setRelations(Event $event, string $status = null, Collection $users): void
    {
        $type = $event->getType();
        if ($type === "task") {
            $this->setTask($event, $status);
        } elseif ($type === "info") {
            $this->setInfo($event, $users);
        }

    }
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

    public function setTask(Event $event, string $taskStatus, Collection $users): void
    {
        $count = count($users);
        $task = (new EventTask())
            ->setTaskStatus($taskStatus)
            ->setSharedWithCount($count);
        $this->em->persist($task);
        foreach ($users as $user) {
            $task->addEventSharedTask($user);
        }
        $event->setTask($task);
    }
    
    public function setInfo(Event $event, Collection $users): EventInfo
    {
        $count = count($users);
        $info = (new EventInfo())
            ->setIsFullyRead(false)
            ->setUserReadInfoCount(0)
            ->setSharedWithCount($count);

        $this->em->persist($info);
        $event->setInfo($info);
        $this->setEventSharedInfo($users, $info);

        return $info;
    }

    public function setEventSharedInfo(Collection $users, EventInfo $info): void
    {
        foreach ($users as $user) {
            $eventSharedInfo = (new EventSharedInfo())
                ->setUser($user)
                ->setEventInfo($info)
                ->setIsRead(false);
            $this->em->persist($eventSharedInfo);
            $info->addEventSharedInfo($eventSharedInfo);
        }
    }


}