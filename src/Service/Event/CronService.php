<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use App\Service\ResponseService;
use App\Service\TagService;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CronService
{
    /**
     * @var DateTimeImmutable The current date.
     */
    protected $now;
    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventRecurringService $eventRecurringService,
        protected ParameterBagInterface $parameterBag,
        protected TagService $tagService,
        protected EventService $eventService,
        protected ResponseService $responseService
    ) {
        $now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
    }

    /**
     * Initializes and processes events for the current day.
     * - Handles yesterday's events.
     * - Creates tags for today's events.
     * - Deletes old tags.
     */
    private function load()
    {
        $todayEvents = $this->handleYesterdayEvents()->getData()[ 'todayEvents' ];
        foreach ($todayEvents as $todayEvent) {
            $this->tagService->createTag($todayEvent);
        }
        $this->tagService->deletePastTag();
        $this->updateAllEventsTimeStamps();
        $this->deleteOldEvents();
    }

    /**
     * Finds events that were due yesterday.
     * 
     * @return array List of events due yesterday.
     */
    public function findYesterdayEvents()
    {
        $dueDate = $this->now->modify('-1 day');
        $query = $this->em->createQuery('SELECT e FROM App\Entity\Event\Event e WHERE e.dueDate = :dueDate');
        $yesterdayEvents = $query->setParameter('dueDate', $dueDate)->getResult();
        return $yesterdayEvents;
    }

    /**
     * Processes yesterday's events to handle overdue tasks or unread information.
     * - Creates today's events based on yesterday's data.
     * - Updates task statuses as necessary.
     * 
     * @return ResponseService Contains the list of today's events and status of the operation.
     */
    public function handleYesterdayEvents(): ResponseService
    {
        try {
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
            $todayEvents = new ArrayCollection();
            foreach ($yesterdayEvents as $yesterdayEvent) {
                $users = $this->eventService->getUsers($yesterdayEvent);
                if ($yesterdayEvent->getTask()) {
                    $taskStatus = $yesterdayEvent->getTask()->getStatus();
                    if ($taskStatus === 'done') {
                        continue;
                    } else {
                        $yesterdayEvent->getTask()->setTaskStatus('unrealised');
                        $todayEvent = $this->createTodayEvent($yesterdayEvent, $users);
                        $todayEvents->add($todayEvent);
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
                    $todayEvent = $this->createTodayEvent($yesterdayEvent, $users);
                    $todayEvents->add($todayEvent);

                } else {
                    continue;
                }
            }
            return $this->responseService::success('Events handled successfully', ['todayEvents' => $todayEvents]);
        } catch (Exception $e) {
            return $this->responseService::error('Events handling failed' . $e->getMessage(), null, 'EVENTS_HANDLING_FAILED');
        }
    }

    /**
     * Creates today's version of an event (recurring or non-recurring).
     * 
     * @param Event $yesterdayEvent The event from yesterday to duplicate.
     * @param Collection $users The users associated with the event.
     * @return Event The new event created for today.
     */
    public function createTodayEvent(Event $yesterdayEvent, Collection $users): ResponseService
    {
        $todayEvent = $yesterdayEvent->isRecurring() ?
            $this->createRecuringTodayEvent($yesterdayEvent, $users) :
            $this->createNonRecuringTodayEvent($yesterdayEvent, $users);
        $this->em->persist($todayEvent);
        $this->em->flush();
        return ResponseService::success('Event created successfully', ['todayEvent' => $todayEvent]);
    }

    /**
     * Creates a non-recurring event for today.
     * 
     * @param Event $yesterdayEvent The event from yesterday to duplicate.
     * @param Collection $users The users associated with the event.
     * @param string|null $taskStatus The status to apply to the new event's task.
     * @return Event The new event created for today.
     */
    public function createNonRecuringTodayEvent(Event $yesterdayEvent, Collection $users, string $taskStatus = null): ResponseService
    {
        try {
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
            $todayEvent->setIsRecurring(false);

            return $this->responseService::success('Event created successfully', ['todayEvent' => $todayEvent]);
        } catch (Exception $e) {
            return $this->responseService::error('Event creation failed' . $e->getMessage(), null, 'EVENT_CREATION_FAILED');
        }
    }

    /**
     * Creates a recurring event for today if no duplicate exists.
     * 
     * @param Event $yesterdayEvent The event from yesterday to duplicate.
     * @param Collection $users The users associated with the event.
     * @return Event The new recurring event created for today.
     */
    public function createRecuringTodayEvent(Event $yesterdayEvent, Collection $users): ResponseService
    {
        try {
            $parentId = $yesterdayEvent->getEventRecurring()->getId();
            $dueDate = $yesterdayEvent->getDueDate();
            // on vérifie si il existe un event recurring de la meme famille , si oui on ne duplicate pas pour eviter un doublon
            $brother = $this->em->createQueryBuilder()
                ->select('e')
                ->from(Event::class, 'e')
                ->where('e.dueDate = :dueDate')
                ->andWhere('e.eventRecurring = :parentId')
                ->setParameter('parentId', $parentId)
                ->setParameter('dueDate', $dueDate)
                ->getQuery()
                ->getResult();
            if ($brother) {
                return $this->responseService::error('Event already created', null, 'EVENT_ALREADY_CREATED');
            }
            $todayEvent = $this->createNonRecuringTodayEvent($yesterdayEvent, $users);
            $todayEvent->setIsRecurring(true);

            return $this->responseService::success('Event created successfully', ['todayEvent' => $todayEvent]);
        } catch (Exception $e) {
            return $this->responseService::error('Event creation failed' . $e->getMessage(), null, 'EVENT_CREATION_FAILED');
        }
    }

    /**
     * Duplicates the base data of an event.
     * 
     * @param Event $originalEvent The event to duplicate.
     * @return Event A new event with duplicated base data.
     */
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

    /**
     * Updates timestamps for events based on their date status or due date.
     * 
     * - Fetches events with the `datestatus` set to "activeDayRange".
     * - Fetches events whose due date matches a calculated date (4 days from now).
     * - Merges the results and updates timestamps for all fetched events.
     * 
     * @return ResponseService Contains the status of the operation and the updated events.
     */
    public function updateAllEventsTimeStamps()
    {
        try {
            $datestatus = "activeDayRange";

            // Fetch events with the 'activeDayRange' datestatus.
            $activeDayRangeEvents = $this->em->createQueryBuilder()
                ->select('e')
                ->from(Event::class, 'e')
                ->where('e.datestatus = :datestatus')
                ->setParameter('datestatus', $datestatus)
                ->getQuery()
                ->getResult();

            // Calculate the due date for the additional events (4 days from now).
            $dueDate = $this->now->modify('+4 days');

            // Fetch events with a matching due date.
            $newActiveDayRangeEvents = $this->em->createQueryBuilder()
                ->select('e')
                ->from(Event::class, 'e')
                ->where('e.dueDate = :dueDate')
                ->setParameter('dueDate', $dueDate)
                ->getQuery()
                ->getResult();

            // Combine both sets of events.
            $events = array_merge($activeDayRangeEvents, $newActiveDayRangeEvents);

            // Update timestamps for each event.
            foreach ($events as $event) {
                $this->eventService->setTimestamps($event);
            }
            $this->em->flush();

            return $this->responseService::success('Events timestamps updated successfully', ['events' => $events]);
        } catch (Exception $e) {
            return $this->responseService::error('Event timestamp update failed: ' . $e->getMessage(), null, 'EVENT_TIMESTAMP_UPDATE_FAILED');
        }
    }
    /**
     * Deletes events older than 30 days from the database.
     * 
     * - Calculates the latest acceptable date (`now - 30 days`).
     * - Fetches all events with a due date earlier than the calculated date.
     * - Removes these events from the database.
     * 
     * @return ResponseService Contains the status of the operation and the deleted events.
     */
    public function deleteOldEvents()
    {
        try {
            // Calculate the cutoff date (30 days ago).
            $latestDate = $this->now->modify('-30 days');

            // Fetch all events with a due date earlier than the cutoff date.
            $oldEvents = $this->em->createQueryBuilder()
                ->select('e')
                ->from(Event::class, 'e')
                ->where('e.dueDate < :latestDate')
                ->setParameter('latestDate', $latestDate)
                ->getQuery()
                ->getResult();

            // Remove each fetched event from the database.
            foreach ($oldEvents as $oldEvent) {
                $this->em->remove($oldEvent);
            }
            $this->em->flush();

            return $this->responseService::success('Old events deleted successfully', ['oldEvents' => $oldEvents]);
        } catch (Exception $e) {
            return $this->responseService::error('Old events deletion failed: ' . $e->getMessage(), null, 'OLD_EVENTS_DELETION_FAILED');
        }
    }

}