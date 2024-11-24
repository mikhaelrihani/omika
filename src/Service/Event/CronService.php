<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use App\Service\Event\TagService;
use App\Utils\ApiResponse;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class CronService
{
    /**
     * @var DateTimeImmutable The current date.
     */
    protected $now;
    protected $todayEventsCreated;
    protected $oldEventsDeleted;
    protected $eventsActivated;

    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventRecurringService $eventRecurringService,
        protected ParameterBagInterface $parameterBag,
        protected TagService $tagService,
        protected EventService $eventService,

    ) {
        $this->now = new DateTimeImmutable('today');
        $this->todayEventsCreated = null;
        $this->oldEventsDeleted = null;
        $this->eventsActivated = null;
    }

    /**
     * Initializes and processes events for the current day by duplicating and updating events from yesterday.
     * 
     * This method orchestrates several operations, each handled by a dedicated method:
     * 
     * - **handleYesterdayEvents**: 
     *     Duplicates events from yesterday for today's date. Tasks that were not completed yesterday are marked 
     *     as "unrealised" and recreated with a "pending" status for today. Information that was not read by users 
     *     yesterday is duplicated for today, with the "isOld" flag set to true for tracking purposes.
     * 
     * - **tagService->createTag**: 
     *     Generates tags for today's events to facilitate categorization and searching.
     * 
     * - **tagService->deletePastTag**: 
     *     Removes outdated tags from the system to maintain data relevance and avoid clutter.
     * 
     * - **updateAllEventsTimeStamps**: 
     *     Updates the timestamps of all events to ensure they reflect the latest changes or activity.
     * 
     * - **deleteOldEvents**: 
     *     Permanently removes events that are no longer relevant (e.g., those past a certain date).
     * 
     * If any of these operations fail, an appropriate error response is returned, ensuring that the process 
     * is interrupted gracefully and the issue is logged with a specific error code.
     *
     * @return ApiResponse A success response if all operations are completed, or an error response if any step fails.
     */
    public function load(): ApiResponse
    {
        $todayEvents = null;

        // Step 1: Handle yesterday's events by duplicating tasks or information for today
        try {
            $todayEvents = $this->handleYesterdayEvents()->getData()[ 'todayEvents' ];
        } catch (Exception $e) {
            return ApiResponse::error('Failed to handle yesterday\'s events: ' . $e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Step 2: Create tags for today's events
        try {
            foreach ($todayEvents as $todayEvent) {
                $this->tagService->createTag($todayEvent);
            }
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create tags: ' . $e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Step 3: Delete old tags
        try {
            $this->tagService->deletePastTag();
        } catch (Exception $e) {
            return ApiResponse::error('Failed to delete old tags: ' . $e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Step 4: Update timestamps for all events
        try {
            $this->updateAllEventsTimeStamps();
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update timestamps: ' . $e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Step 5: Delete old events
        try {
            $this->deleteOldEvents();
        } catch (Exception $e) {
            return ApiResponse::error('Failed to delete old events: ' . $e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Final response: Success

        return ApiResponse::success(
            "Cron job is done, todayEventsCreated = {$this->todayEventsCreated}, oldEventsDeleted = {$this->oldEventsDeleted}, eventsActivated = {$this->eventsActivated}",
            null,
            Response::HTTP_OK
        );
    }






    //! Step 1: Handle yesterday's events by duplicating tasks or information for today --------------------------------------------


    /**
     * Finds events from yesterday that have not been processed yet.
     * 
     * @return Collection The list of yesterday's events that have not been processed.
     */
    public function findYesterdayEvents(): Collection
    {
        $dueDate = $this->now->modify('-1 day')->format('Y-m-d');

        $yesterdayEvents = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.dueDate = :dueDate')
            ->andWhere('e.isProcessed = false') // Exclure les événements déjà traités
            ->setParameter('dueDate', $dueDate)
            ->getQuery()
            ->getResult();

        return new ArrayCollection($yesterdayEvents);
    }

    /**
     * Processes yesterday's events to handle overdue tasks or unread information.
     * - Creates today's events based on yesterday's data.
     * - Updates task statuses as necessary.
     * 
     * @return ApiResponse Contains the list of today's events and status of the operation.
     */
    public function handleYesterdayEvents(): ApiResponse
    {
        try {

            $yesterdayEvents = $this->findYesterdayEvents();
            $this->todayEventsCreated = count($yesterdayEvents);
            // Vérification si la collection est vide
            if ($yesterdayEvents->isEmpty()) {
                return ApiResponse::success('No events found for yesterday. Nothing to process.', ['todayEvents' => []], Response::HTTP_OK);
            }

            $todayEvents = new ArrayCollection();

            foreach ($yesterdayEvents as $yesterdayEvent) {
                $users = $this->eventService->getUsers($yesterdayEvent);

                // Marquer l'événement comme traité
                $yesterdayEvent->setIsProcessed(true);

                // Gestion des tâches
                if ($yesterdayEvent->getTask()) {
                    $this->processTaskEvent($yesterdayEvent, $users, $todayEvents);
                    continue;
                }

                // Gestion des informations
                if ($yesterdayEvent->getInfo()) {
                    $this->processInfoEvent($yesterdayEvent, $todayEvents);
                    continue;
                }

                $this->em->flush();
            }

            return ApiResponse::success('Events handled successfully', ['todayEvents' => $todayEvents], Response::HTTP_OK);
        } catch (Exception $e) {
            return ApiResponse::error(
                'Events handling failed: ' . $e->getMessage(),
                null,
            );
        }
    }



    /**
     * Process a task-based event.
     *
     * @param Event $yesterdayEvent
     * @param Collection $users
     * @param ArrayCollection $todayEvents
     */
    private function processTaskEvent(Event $yesterdayEvent, Collection $users, ArrayCollection $todayEvents): void
    {
        $taskStatus = $yesterdayEvent->getTask()->getTaskStatus();

        if ($taskStatus === 'done') {
            return; // Skip completed tasks
        }

        // Update yesterday's task status
        $yesterdayEvent->getTask()->setTaskStatus('unrealised');

        // Create today's event
        $todayEvent = $this->createTodayEvent($yesterdayEvent, $users, $taskStatus);

        if ($todayEvent) {
            $todayEvents->add($todayEvent);
        }
    }

    /**
     * Process an info-based event.
     *
     * @param Event $yesterdayEvent
     * @param ArrayCollection $todayEvents
     */
    private function processInfoEvent(Event $yesterdayEvent, ArrayCollection $todayEvents): void
    {
        if ($yesterdayEvent->getInfo()->isFullyRead()) {
            return; // Skip fully read information
        }

        // Filter users who have not read the information
        $users = $this->getUnreadInfoUsers($yesterdayEvent);

        if ($users->isEmpty()) {
            return; // No unread users to assign
        }

        // Create today's event
        $todayEvent = $this->createTodayEvent($yesterdayEvent, $users);

        if ($todayEvent) {
            $todayEvents->add($todayEvent);
        }
    }

    /**
     * Get users who have not read the information from yesterday's event.
     *
     * @param Event $yesterdayEvent
     * @return Collection
     */
    private function getUnreadInfoUsers(Event $yesterdayEvent): Collection
    {
        $users = new ArrayCollection();

        foreach ($yesterdayEvent->getInfo()->getSharedWith() as $userInfo) {
            if (!$userInfo->isRead()) {
                $users->add($userInfo->getUser());
            }
        }

        return $users;
    }

    /**
     * Creates an event for today based on yesterday's event data.
     *
     * @param Event $yesterdayEvent The event to duplicate.
     * @param Collection $users The users associated with the event.
     * @param string|null $taskStatus The status of the task associated with the event.
     * @return Event|null The new event for today, or null if the event is recurring and has a brother event for today.
     */
    public function createTodayEvent(Event $yesterdayEvent, Collection $users, ?string $taskStatus = null): ?Event
    {
        if ($yesterdayEvent && $yesterdayEvent->isRecurring() && $this->hasBrotherToday($yesterdayEvent)) {
            return null;
        }

        $todayEvent = $this->prepareEventForToday($yesterdayEvent, $users, $taskStatus);

        $this->em->persist($todayEvent);
        $this->em->flush();

        return $todayEvent;
    }

    /**
     * Prepares a duplicated event for today based on yesterday's event data.
     *
     * @param Event $yesterdayEvent
     * @param Collection $users
     * @param string|null $taskStatus
     * @return Event
     */
    private function prepareEventForToday(Event $yesterdayEvent, Collection $users, ?string $taskStatus = null): Event
    {
        $todayEvent = $this->duplicateEventBase($yesterdayEvent);

        $this->eventService->setRelations($todayEvent, $users, "late");

        $this->updateTaskOrInfoStatus($yesterdayEvent, $todayEvent, $taskStatus);

        $this->handleRecurringStatus($yesterdayEvent, $todayEvent);

        $this->updateEventDates($yesterdayEvent, $todayEvent);

        return $todayEvent;
    }

    /**
     * Updates the task status or marks the info as old.
     *
     * @param Event $yesterdayEvent
     * @param Event $todayEvent
     * @param string|null $taskStatus
     */
    private function updateTaskOrInfoStatus(Event $yesterdayEvent, Event $todayEvent, ?string $taskStatus): void
    {
        $todayEventTask = $todayEvent->getTask();

        if ($todayEventTask) {
            $isPending = $taskStatus === 'pending' || $yesterdayEvent->getTask()?->isPending();
            $yesterdayEvent->getTask()?->setPending($isPending);
            $todayEventTask->setPending($isPending);
        } else {
            $todayEvent->getInfo()->setOld(true);
        }
    }

    /**
     * Updates the due dates for the new event.
     *
     * @param Event $yesterdayEvent
     * @param Event $todayEvent
     */
    private function updateEventDates(Event $yesterdayEvent, Event $todayEvent): void
    {
        $dueDate = $yesterdayEvent->getDueDate()->modify("+1 day");//! si on utilise $this->now et que l'on passe le cronJob alors on a une boucle infini de creatio d objet a la date de yesterday
        $todayEvent->setDueDate($dueDate);
        $todayEvent->setFirstDueDate($yesterdayEvent->getFirstDueDate());
        $this->eventService->setTimestamps($todayEvent);
    }

    /**
     * Handles the recurring status of today's event based on yesterday's event.
     * 
     * - If the yesterday's event is marked as recurring, links today's event to the recurring parent.
     * - If the recurring parent is missing, the process stops for the current event, but the loop can continue.
     * - Ensures that today's event is marked as recurring if linked to a recurring parent.
     * 
     * @param Event $yesterdayEvent The event from yesterday being processed. Can be null or non-recurring.
     * @param Event $todayEvent The newly created event for today that needs its status updated.
     * 
     * @return void
     */
    public function handleRecurringStatus(Event $yesterdayEvent, Event $todayEvent): void
    {
        if (!$yesterdayEvent || !$yesterdayEvent->isRecurring()) {
            return; // Rien à faire si l'événement d'hier n'existe pas ou n'est pas récurrent
        }
        $recurringParent = $yesterdayEvent->getEventRecurring();
        if (!$recurringParent) {
            return;// Continuer avec les autres événements
        } else {
            $todayEvent->setIsRecurring(true);
            $recurringParent->addEvent($todayEvent);
        }
    }

    /**
     * Checks if an event has a brother event for today.
     * 
     * @param Event $yesterdayEvent The event to check.
     * @return bool True if the event has a brother for today, false otherwise.
     */
    public function hasBrotherToday(Event $yesterdayEvent): bool|null
    {

        $parentId = $yesterdayEvent->getEventRecurring()->getId();

        $dueDate = $yesterdayEvent->getDueDate()->modify("+1 day")->format('Y-m-d');

        $brother = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.dueDate = :dueDate')
            ->andWhere('e.eventRecurring = :parentId')
            ->setParameter('parentId', $parentId)
            ->setParameter('dueDate', $dueDate)
            ->getQuery()
            ->getResult();
        return $brother ? true : false;
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
            ->setSection($originalEvent->getSection())
            ->setIsProcessed(false);
        return $event;
    }




    //! Step 4: Update timestamps for all events -----------------------------------------------------------------------------------
    /**
     * Updates timestamps for events based on their date status or due date.
     * 
     * - Fetches events with the `datestatus` set to "activeDayRange".
     * - Fetches events whose due date matches a calculated date (4 days from now).
     * - Merges the results and updates timestamps for all fetched events.
     * 
     * @return ApiResponse Contains the status of the operation and the updated events.
     */
    public function updateAllEventsTimeStamps(): ApiResponse
    {
        try {

            $datestatus = "activeDayRange";

            // Fetch events with the 'activeDayRange' datestatus.
            $activeDayRangeEvents = $this->em->createQueryBuilder()
                ->select('e')
                ->from(Event::class, 'e')
                ->where('e.date_status = :date_status')
                ->setParameter('date_status', $datestatus)
                ->getQuery()
                ->getResult();

            // Calculate the due date for the additional events (4 days from now).
            $dueDate = $this->now->modify('+7 days')->format('Y-m-d');

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
            // count the number of events to display in the final cronJob response.
            $eventsActivated = array_filter(
                $newActiveDayRangeEvents,
                fn($newActiveDayRangeEvent): bool => $newActiveDayRangeEvent->getDateStatus() !== "activeDayRange"
            );
            $this->eventsActivated = count($eventsActivated);
            // Update timestamps for each event.
            foreach ($events as $event) {
                $this->eventService->setTimestamps($event);
            }
            $this->em->flush();

            return ApiResponse::success('Events timestamps updated successfully', ['events' => $events], Response::HTTP_OK);
        } catch (Exception $e) {
            return ApiResponse::error('Event timestamp update failed: ' . $e->getMessage(), null);
        }
    }



    //! Step 5: Delete old events ---------------------------------------------------------------------------------------------------
    /**
     * Deletes events older than 30 days from the database.
     * 
     * - Calculates the latest acceptable date (`now - 30 days`).
     * - Fetches all events with a due date earlier than the calculated date.
     * - Removes these events from the database.
     * 
     * @return ApiResponse Contains the status of the operation and the deleted events.
     */
    public function deleteOldEvents()
    {
        try {
            // Calculate the cutoff date (30 days ago).
            $latestDate = $this->now->modify('-30 days')->format('Y-m-d');

            // Fetch all events with a due date earlier than the cutoff date.
            $oldEvents = $this->em->createQueryBuilder()
                ->select('e')
                ->from(Event::class, 'e')
                ->where('e.dueDate < :latestDate')
                ->setParameter('latestDate', $latestDate)
                ->getQuery()
                ->getResult();

            // count the number of old events to display in the final cronJob response.
            $this->oldEventsDeleted = count($oldEvents);

            // Remove each fetched event from the database.
            foreach ($oldEvents as $oldEvent) {
                $this->em->remove($oldEvent);
            }
            $this->em->flush();
            return ApiResponse::success('Old events deleted successfully', ['oldEvents' => $oldEvents]);
        } catch (Exception $e) {
            return ApiResponse::error('Old events deletion failed: ' . $e->getMessage());
        }
    }

}