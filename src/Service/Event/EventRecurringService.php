<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventRecurring;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\ResponseService;
use App\Service\TagService;
use Doctrine\Common\Collections\ArrayCollection;

class EventRecurringService
{
    protected $now;
    protected $activeDayStart;
    protected $activeDayEnd;

    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventService $eventService,
        protected TagService $tagService,
        protected ParameterBagInterface $parameterBag
    ) {
        $this->now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
        $this->activeDayStart = $this->parameterBag->get('active_day_start');
        $this->activeDayEnd = $this->parameterBag->get('active_day_end');
    }



    /**
     * Retrieves child events that are marked as recurring from the database.
     *
     * This method queries the database to fetch all events that have the "isRecurring" flag set to true.
     * It returns the retrieved child events along with a success message, or an error message if the operation fails.
     *
     * @return ResponseService Response object indicating success or error with the list of children events if successful.
     */
    public function getChildrensFromDatabase(): ResponseService
    {
        try {
            $childrens = $this->eventRepository->findBy(["isRecurring" => true]);
            return ResponseService::success('Children events retrieved successfully', ['childrens' => $childrens]);
        } catch (\Exception $e) {
            return ResponseService::error('Error retrieving children events: ' . $e->getMessage());
        }
    }


    /**
     * Creates child events for a recurring event and updates associated tags.
     *
     * This method generates child events based on the recurrence settings of the given recurring event.
     * For each child event created, a tag is created or updated using the tag service.
     *
     * @param EventRecurring $eventRecurring The recurring event for which children events are to be created.
     *
     * @return void
     */
    public function createChildrens(EventRecurring $eventRecurring): void
    {
        $childrens = $this->handleRecurrenceType($eventRecurring);
        foreach ($childrens as $child) {
            $this->tagService->createOrUpdateTag($child);
        }
    }

    /**
     * Gère le type de récurrence de l'événement parent.
     *
     * @param EventRecurring $parent L'événement parent récurrent.
     * @return ResponseService
     */
    public function handleRecurrenceType(EventRecurring $parent): ResponseService
    {
        try {
            $recurrenceType = $parent->getRecurrenceType();
            switch ($recurrenceType) {
                case "monthDays":
                    $this->handleMonthDays($parent);
                    break;
                case "weekDays":
                    $this->handleWeekdays($parent);
                    break;
                case "periodDates":
                    $this->handlePeriodDates($parent);
                    break;
                case "everyday":
                    $this->handleEveryday($parent);
                    break;
                default:
                    return ResponseService::error('Recurrence type not found');
            }
            return ResponseService::success('Recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling recurrence type: ' . $e->getMessage());
        }
    }

    /**
     * Gère la récurrence par jours de mois.
     *
     * @param EventRecurring $parent
     * @return ResponseService
     */
    public function handleMonthDays(EventRecurring $parent): ResponseService
    {
        try {
            $monthDays = $parent->getMonthDays();
            [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);

            $currentMonthDate = $earliestCreationDate->modify('first day of this month');
            $endPeriodDate = $latestCreationDate->modify('first day of next month');

            while ($currentMonthDate <= $endPeriodDate) {
                foreach ($monthDays as $monthDay) {
                    $day = $monthDay->getDay();
                    $dueDate = $currentMonthDate->modify("+{$day} days -1 day");

                    if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                        $this->createOneChild($parent, $dueDate);
                    }
                }
                $currentMonthDate = $currentMonthDate->modify('first day of next month');
            }
            return ResponseService::success('Month days recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling month days recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Gère la récurrence par jours de la semaine.
     *
     * @param EventRecurring $parent
     * @return ResponseService
     */
    public function handleWeekdays(EventRecurring $parent): ResponseService
    {
        try {
            $weekDays = $parent->getWeekDays();
            [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);

            $currentWeekDate = $earliestCreationDate->modify('this week');

            while ($currentWeekDate <= $latestCreationDate) {
                foreach ($weekDays as $weekDay) {
                    $day = $weekDay->getDay();
                    $dueDate = $currentWeekDate->modify("+{$day} days -1 day");

                    if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                        $this->createOneChild($parent, $dueDate);
                    }
                }
                $currentWeekDate = $currentWeekDate->modify('next week');
            }
            return ResponseService::success('Weekdays recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling weekdays recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Gère la récurrence par dates fixes.
     *
     * @param EventRecurring $parent
     * @return ResponseService
     */
    public function handlePeriodDates(EventRecurring $parent): ResponseService
    {
        try {
            [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);
            $dates = $parent->getPeriodDates();

            foreach ($dates as $date) {
                $dueDate = $date->getDate();

                if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                    $this->createOneChild($parent, $dueDate);
                }
            }
            return ResponseService::success('Period dates recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling period dates recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Gère la récurrence tous les jours.
     *
     * @param EventRecurring $parent
     * @return ResponseService
     */
    public function handleEveryday(EventRecurring $parent): ResponseService
    {
        try {
            [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);
            $diff = (int) $earliestCreationDate->diff($latestCreationDate)->format('%r%a');

            for ($i = 0; $i <= $diff; $i++) {
                $dueDate = $earliestCreationDate->modify("+{$i} day");

                if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                    $this->createOneChild($parent, $dueDate);
                }
            }
            return ResponseService::success('Everyday recurrence handled successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error handling everyday recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Vérifie si une date est dans la période cible.
     *
     * @param DateTimeImmutable $dueDate
     * @param DateTimeImmutable $earliestCreationDate
     * @param DateTimeImmutable $latestCreationDate
     * @return bool
     */
    public function isWithinTargetPeriod(DateTimeImmutable $dueDate, DateTimeImmutable $earliestCreationDate, DateTimeImmutable $latestCreationDate): bool
    {
        return $dueDate >= $earliestCreationDate && $dueDate <= $latestCreationDate;
    }

    /**
     * Calcule les limites de la période en fonction des paramètres de l'événement récurrent.
     *
     * @param EventRecurring $parent
     * @return array
     */
    private function calculatePeriodLimits(EventRecurring $parent): array|ResponseService
    {
        try {
            $latestDate = (new DateTimeImmutable($this->now->format('Y-m-d')))->modify("+{$this->activeDayEnd} day");
            $latestCreationDate = min($latestDate, $parent->getPeriodeEnd());

            $earliestCreationDate = max($this->now, $parent->getPeriodeStart());
            $earliestCreationDate = new DateTimeImmutable($earliestCreationDate->format('Y-m-d'));

            return [$earliestCreationDate, $latestCreationDate];
        } catch (\Exception $e) {
            return ResponseService::error('Error calculating period limits: ' . $e->getMessage());
        }
    }

    /**
     * Crée un événement enfant pour un événement récurrent.
     *
     * @param EventRecurring $parent
     * @param DateTimeImmutable $dueDate
     * @return void
     */
    public function createOneChild(EventRecurring $parent, DateTimeImmutable $dueDate): ResponseService
    {
        try {
            $child = $this->setOneChildBase($parent);
            $child->setDueDate($dueDate);
            $this->eventService->setTimestamps($child);

            $users = $parent->getSharedWith();
            $status = $parent->getType() ? "todo" : null;
            $this->eventService->setRelations($child, $status, $users);

            $this->em->persist($child);
            $parent->addEvent($child);
            $this->em->flush();
            return ResponseService::success('Child event created successfully');
        } catch (\Exception $e) {
            return ResponseService::error('Error creating child event: ' . $e->getMessage());
        }
    }

    /**
     * Définit les propriétés de base d'un événement enfant.
     *
     * @param EventRecurring $parent
     * @return Event
     */
    public function setOneChildBase(EventRecurring $parent): Event
    {
        $child = (new Event())
            ->setDescription($parent->getDescription())
            ->setIsImportant(false)
            ->setSide($parent->getSide())
            ->setTitle($parent->getTitle())
            ->setCreatedBy($parent->getCreatedBy()->getFullName())
            ->setUpdatedBy($parent->getUpdatedBy()->getFullName())
            ->setType($parent->getType())
            ->setSection($parent->getSection())
            ->setIsRecurring(true);

        return $child;
    }

    /**
     * Updates the status of events associated with a recurring event.
     *
     * This method processes the children events of the given recurring event to ensure that:
     * - All tasks with a "todo" status are removed along with their tag counters.
     * - Any task with a status other than "todo" is updated to "warning".
     * - All infos are removed along with their tag counters.
     * - Tasks or infos with user interactions are flagged as "obsolete" and require user action;
     *   User update them by deleting it or keeping it and manage the event status as he wants. 
     * After processing the children, the method creates new child events
     * based on the updated recurring event.
     *
     * @param EventRecurring $eventRecurring The recurring event whose child events are to be updated.
     * 
     * @return ResponseService Response object indicating success or error.
     */
    public function updateEventStatusAfterRecurringEventUpdate(EventRecurring $eventRecurring): ResponseService
    {
        try {
            $childrens = $eventRecurring->getEvents();
            foreach ($childrens as $child) {
                // Process only future events
                if ($child->getDueDate() >= $this->now) {
                    if ($child->getType() === 'task') {
                        if ($child->getTask()->getTaskStatus() === "todo") {
                            $users = $child->getTask()->getSharedWith();
                            $this->eventService->removeEventAndUpdateTagCounters($child, $users);
                        } else {
                            $child->getTask()->setTaskStatus("warning");
                        }
                    } elseif ($child->getType() === 'info') {
                        $shareWith = $child->getInfo()->getSharedWith();
                        $users = new ArrayCollection();

                        foreach ($shareWith as $userInfo) {
                            $users->add($userInfo->getUser());
                        }
                        $this->eventService->removeEventAndUpdateTagCounters($child, $users);

                    } else {
                        continue;// Skip events of unknown type
                    }
                } else {
                    continue; // Skip past events
                }
            }
            $this->em->flush();
            $this->createChildrens($eventRecurring);

            return ResponseService::success('Recurring event children updated successfully.');
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return ResponseService::error(
                'An error occurred while updating recurring event children: ' . $e->getMessage(),
                null,
                'RECURRING_EVENT_UPDATE_FAILED'
            );
        }
    }
}