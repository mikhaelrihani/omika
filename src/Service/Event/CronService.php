<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CronService
{

    protected DateTimeImmutable $now;
    protected int $todayEventsCreated;
    protected int $oldEventsDeleted;
    protected int $eventsActivated;
    protected int $recurringChildrensCreated;

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
        $this->todayEventsCreated = 0;
        $this->oldEventsDeleted = 0;
        $this->eventsActivated = 0;
        $this->recurringChildrensCreated = 0;
    }

    /**
     * Exécute une série de tâches en tant que processus planifié pour gérer les événements.
     *
     * Cette méthode exécute les étapes suivantes dans l'ordre :
     * 1. Gère les événements d'hier et crée les événements pour aujourd'hui.
     * 2. Crée des tags pour les événements d'aujourd'hui.
     * 3. Supprime les tags obsolètes.
     * 4. Met à jour les horodatages de tous les événements.
     * 5. Supprime les anciens événements.
     *
     * Si une des étapes échoue, elle retourne une réponse d'erreur immédiatement avec les détails.
     *
     * @return JsonResponse
     * - En cas de succès : retourne un message contenant les actions effectuées.
     * - En cas d'échec : retourne un message d'erreur spécifique à l'étape ayant échoué.
     */

    public function load(): JsonResponse
    {

        // Définir les étapes à exécuter
        $steps = [
            'handleYesterdayEvents'           => function () use (&$todayEvents): void {
                $todayEvents = $this->handleYesterdayEvents() ?? new ArrayCollection();
            },
            'createTagsForToday'              => function () use (&$todayEvents): void {
                foreach ($todayEvents as $todayEvent) {
                    $this->tagService->createTag($todayEvent);
                }
            },
            'deletePastTags'                  => fn(): null => $this->deletePastTag(),
            'updateAllEventsTimeStamps'       => fn(): Collection => $this->updateAllEventsTimeStamps(),
            'deleteOldEvents'                 => fn(): null => $this->deleteOldEvents(),
            'createRecurringChildrens'        => function () use (&$createdChildrens): void {
                $createdChildrens = $this->createRecurringChildrens() ?? new ArrayCollection();
            },
            'createTagsForRecurringChildrens' => function () use (&$createdChildrens): void {
                foreach ($createdChildrens as $createdChildren) {
                    $this->tagService->createTag($createdChildren);
                }
            },

        ];

        // Exécuter chaque étape et capturer les exceptions
        foreach ($steps as $stepName => $step) {
            try {
                $step();
            } catch (Exception $e) {

                $response = ApiResponse::error(
                    "Step -{$stepName}- failed :" . $e->getMessage(),
                    null,
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
                return new JsonResponse($response->getMessage(), $response->getStatusCode());
            }
        }

        // Toutes les étapes réussies
        $response = ApiResponse::success(
            "Cron job completed successfully. Today Events Created: {$this->todayEventsCreated}, Old Events Deleted: {$this->oldEventsDeleted}, Events Activated: {$this->eventsActivated}, RecurringChildrensCreated:{$this->recurringChildrensCreated}.",
            null,
            Response::HTTP_OK
        );

        return new JsonResponse($response->getMessage(), $response->getStatusCode());

    }



    //! Step 1: Handle yesterday's events by duplicating tasks or information for today --------------------------------------------


    /**
     * Finds events from yesterday that have not been processed yet.
     * 
     * @return Collection The list of yesterday's events that have not been processed.
     */
    private function findYesterdayEvents(): Collection
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
     * Handles yesterday's events by duplicating tasks or information for today.
     *
     * @return Collection The list of today's events that were created.
     */
    private function handleYesterdayEvents(): Collection
    {
        $yesterdayEvents = $this->findYesterdayEvents();

        $todayEvents = new ArrayCollection();
        if ($yesterdayEvents->isEmpty()) {
            return $todayEvents;
        }

        foreach ($yesterdayEvents as $yesterdayEvent) {
            $users = $this->eventService->getUsers($yesterdayEvent);
            $yesterdayEvent->setIsProcessed(true);

            if ($yesterdayEvent->getTask()) {
                $this->processTaskEvent($yesterdayEvent, $users, $todayEvents);
                continue;
            }

            if ($yesterdayEvent->getInfo()) {
                $this->processInfoEvent($yesterdayEvent, $todayEvents);
                continue;
            }

            $this->em->flush();
        }
        $this->todayEventsCreated = count($todayEvents);
        return $todayEvents;
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
     * Retrieves the users who have not read the information.
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
     * Prepares an event for today based on yesterday's event data.
     *
     * @param Event $yesterdayEvent The event to duplicate.
     * @param Collection $users The users associated with the event.
     * @param string|null $taskStatus The status of the task associated with the event.
     * @return Event The new event for today.
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
    private function handleRecurringStatus(Event $yesterdayEvent, Event $todayEvent): void
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
    private function hasBrotherToday(Event $yesterdayEvent): bool|null
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
    private function duplicateEventBase(Event $originalEvent): Event
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
            ->setPublished($originalEvent->isPublished())
            ->setIsProcessed(false);
        return $event;
    }




    //! Step 4: Update timestamps for all events -----------------------------------------------------------------------------------

    /**
     * Updates the timestamps for all events in the database.
     * 
     * - Fetches events with the 'activeDayRange' datestatus.
     * - Calculates the due date for the additional events (7 days from now).
     * - Fetches events with a matching due date.
     * - Combines both sets of events.
     * - Updates timestamps for each event.
     * 
     * @return Collection The list of events that were processed.
     */
    private function updateAllEventsTimeStamps(): Collection
    {
        $datestatus = "activeDayRange";

        // Fetch events with the 'activeDayRange' datestatus.
        $activeDayRangeEvents = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.date_status = :date_status')
            ->setParameter('date_status', $datestatus)
            ->getQuery()
            ->getResult();

        // Calculate the due date for the additional events (7 days from now).
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

        // Count the number of activated events for the final cronJob response.
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

        return new ArrayCollection($events); // Retourne les événements traités
    }



    //! Step 5: Delete old events ---------------------------------------------------------------------------------------------------

    /**
     * Deletes events that are older than 30 days.
     * 
     * - Fetches all events with a due date earlier than the cutoff date.
     * - Removes each fetched event from the database.
     * 
     * @return null
     */
    private function deleteOldEvents(): null
    {
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
        return null;
    }


    //! step 6 Create new event from eventRecurringParents ----------------------------------------------------------------

    /**
     * Creates recurring children events from eventRecurringParents.
     * 
     * - Fetches all eventRecurringParents from the database.
     * - Creates children events for each parent.
     * - Returns the list of created children events.
     * 
     * @return Collection The list of created children events.
     */
    private function createRecurringChildrens(): Collection
    {
        $eventRecurrings = $this->eventRecurringRepository->findAll();

        $createdChildrens = new ArrayCollection();
        if (empty($eventRecurrings)) {
            return $createdChildrens;
        }

        foreach ($eventRecurrings as $eventRecurring) {
            $response = $this->eventRecurringService->createChildrens($eventRecurring, true);
            if (!$response->isEmpty()) {
                foreach ($response as $createdChildren) {
                    $createdChildrens->add($createdChildren);
                }
            }
        }

        $this->recurringChildrensCreated = count($createdChildrens);
        return $createdChildrens;

    }

    /**
     * Deletes tags that are older than yesterday.
     * we delete past tags because they are no longer relevant , tags are made to inform about the current day events or future events only.
     * This method identifies tags that have a `day` field corresponding to either
     * yesterday or the day before yesterday and deletes them from the database.
     * The operation directly interacts with the database using Doctrine's QueryBuilder
     * for optimal performance.
     *
     * @return null
     */
    private function deletePastTag(): null
    {
        $today = (new DateTimeImmutable("today"))->format('Y-m-d');

        $tags = $this->em->createQueryBuilder()
            ->select('t')
            ->from(Tag::class, 't')
            ->where('t.day < :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();
        foreach ($tags as $tag) {
            $this->em->remove($tag);
        }
        $this->em->flush();
        return null;
    }
}