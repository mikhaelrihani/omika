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

class EventService
{
    private $now;
    public function __construct(
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
    ) {
        $now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
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
            ->setSection($data[ "section" ]);

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
    public function setTimeStamps(Event $event): void
    {
        $event
            ->setDueDate($this->now)
            ->setActiveDay(0)
            ->setDateStatus("activeDayRange");
    }

    public function setTask(Event $event, string $taskStatus): void
    {
        $task = (new EventTask())
            ->setTaskStatus($taskStatus);
        $this->em->persist($task);

        $event->setTask($task);
    }
    public function setInfo(Event $event, Collection $users): EventInfo
    {
        $info = (new EventInfo())
            ->setIsFullyRead(false)
            ->setUserReadInfoCount(0);
        $count = count($users);
        $info->setSharedWithCount($count);

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