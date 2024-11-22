<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventRecurring;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\Event\TagService;
use App\Utils\ApiResponse;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;

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
        $this->now = new DateTimeImmutable('today');
        $this->activeDayStart = $this->parameterBag->get('activeDayStart');
        $this->activeDayEnd = $this->parameterBag->get('activeDayEnd');
    }

    /**
     * Retrieves child events that are marked as recurring from the database.
     *
     * This method queries the database to fetch all events that have the "isRecurring" flag set to true.
     * It returns the retrieved child events along with a success message, or an error message if the operation fails.
     *
     * @return ApiResponse Response object indicating success or error with the list of children events if successful.
     */
    public function getAllChildrensFromDatabase(): ApiResponse
    {
        try {
            $childrens = $this->eventRepository->findBy(["isRecurring" => true]);
            return ApiResponse::success('Children events retrieved successfully', ['childrens' => $childrens]);
        } catch (Exception $e) {
            return ApiResponse::error('Error retrieving children events: ' . $e->getMessage());
        }
    }
    /**
     * Handles the update of the children events of a recurring event.
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
     * @return ApiResponse Response object indicating success or error.
     */
    public function handleRecurringEventUpdate(EventRecurring $eventRecurring): ApiResponse
    {
        try {
            $childrens = $eventRecurring->getEvents();
            foreach ($childrens as $child) {
                // Process only future events
                if ($child->getDueDate() >= $this->now) {
                    if ($child->getType() === 'task') {
                        if ($child->getTask()->getTaskStatus() === "todo") {
                            $users = $child->getTask()->getSharedWith();
                            $this->eventService->removeEventAndUpdateTagCounters($child);
                        } else {
                            $child->getTask()->setTaskStatus("warning");
                        }
                    } elseif ($child->getType() === 'info') {
                        $shareWith = $child->getInfo()->getSharedWith();
                        $users = new ArrayCollection();

                        foreach ($shareWith as $userInfo) {
                            $users->add($userInfo->getUser());
                        }
                        $this->eventService->removeEventAndUpdateTagCounters($child);

                    } else {
                        continue;// Skip events of unknown type
                    }
                } else {
                    continue; // Skip past events
                }
            }
            $this->em->flush();
            $this->createChildrens($eventRecurring);

            return ApiResponse::success('Recurring event children updated successfully.');
        } catch (Exception $e) {
            // Handle any unexpected errors
            return ApiResponse::error(
                'An error occurred while updating recurring event children: ' . $e->getMessage(),
                null,
                'RECURRING_EVENT_UPDATE_FAILED'
            );
        }
    }

    /**
     * Deletes a recurring event along with its associated child events and updates tag counters.
     *
     * This method removes all child events associated with the given recurring event, updates the tag counters
     * for all users associated with the event, and then deletes the recurring event itself from the database.
     *
     * @param EventRecurring $eventRecurring The recurring event to be deleted.
     *
     * @return ApiResponse Response object indicating success or failure of the operation.
     *
     * @throws \Exception If an error occurs during the deletion process.
     */
    public function deleteRecurringEvent(EventRecurring $eventRecurring): ApiResponse
    {
        try {
            $childrens = $eventRecurring->getEvents();
            $users = $eventRecurring->getSharedWith();
            foreach ($childrens as $child) {
                $this->eventService->removeEventAndUpdateTagCounters($child);
            }
            $this->em->remove($eventRecurring);
            $this->em->flush();
            return ApiResponse::success('Recurring event deleted successfully');

        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while deleting recurring event: ' . $e->getMessage());
        }
    }

    /**
     * Creates children events for a recurring event and creates their associated tags.
     * 
     * This method generates child events for a recurring event based on its recurrence type
     * and ensures that each child event has its associated tag properly handled.
     * 
     * @param EventRecurring $eventRecurring The recurring event for which child events will be created.
     * 
     * @return ApiResponse Returns a success response with the created child events if the operation is successful.
     *                         Returns an error response if an exception occurs during the process.
     * 
     * @throws \Exception If an error occurs during the creation of child events or tag handling, it is caught
     *                    and an error response is returned.
     */
    public function createChildrens(EventRecurring $eventRecurring): ApiResponse
    {
        try {
            $childrens = $this->handleRecurrenceType($eventRecurring);
            foreach ($childrens as $child) {
                $this->tagService->createTag($child);
            }
            return ApiResponse::success('Recurring event children created successfully', ['childrens' => $childrens]);
        } catch (Exception $e) {

            return ApiResponse::error(
                'An error occurred while creating recurring event children: ' . $e->getMessage(),
                null,
                'RECURRING_EVENT_CREATION_FAILED'
            );
        }

    }

    /**
     * Creates a single child event for a recurring event.
     *
     * This method creates a child event based on the given recurring event and due date.
     * The child event is created with the same properties as the parent event, except for the timestamps.
     * The child event is then persisted to the database and associated with the parent event.
     *
     * @param EventRecurring $parent The parent recurring event for which the child event is to be created.
     * @param DateTimeImmutable $dueDate The due date for the child event.
     *
     * @return ApiResponse Returns a success response if the child event is created successfully,
     *                         or an error response if an exception occurs during the process.
     */
    public function createOneChild(EventRecurring $parent, DateTimeImmutable $dueDate): ApiResponse
    {
        try {
            $child = $this->setOneChildBase($parent);
            $child->setDueDate($dueDate);
            $this->eventService->setTimestamps($child);

            $users = $parent->getSharedWith();
            $status = $parent->getType() ? "todo" : null;
            $this->eventService->setRelations($child, $users);

            $this->em->persist($child);
            $parent->addEvent($child);
            $this->em->flush();
            return ApiResponse::success('Child event created successfully');
        } catch (Exception $e) {
            return ApiResponse::error('Error creating child event: ' . $e->getMessage());
        }
    }

    /**
     * Sets the base properties of a child event based on a recurring event.
     *
     * This method creates a new child event based on the properties of the given recurring event.
     * The child event is created with the same properties as the parent event, except for the timestamps.
     *
     * @param EventRecurring $parent The recurring event from which the child event properties are derived.
     *
     * @return Event The child event with the base properties set from the parent event.
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
     * Handles the recurrence type of a recurring event.
     *
     * This method processes the recurrence type of the given recurring event and creates child events accordingly.
     * The method calls the appropriate handler method based on the recurrence type of the event.
     *
     * @param EventRecurring $parent The recurring event whose recurrence type is to be handled.
     *
     * @return ApiResponse Response object indicating success or error.
     */
    public function handleRecurrenceType(EventRecurring $parent): ApiResponse
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
                    return ApiResponse::error('Recurrence type not found');
            }
            return ApiResponse::success('Recurrence handled successfully');
        } catch (Exception $e) {
            return ApiResponse::error('Error handling recurrence type: ' . $e->getMessage());
        }
    }

    /**
     * Handles the generation of recurring child events based on specific days of the month.
     * 
     * This method processes a parent recurring event and creates child events on specified
     * days of the month, within a defined period of recurrence.
     * 
     * @param EventRecurring $parent The parent recurring event that contains the configuration for the recurrence.
     * 
     * @return ApiResponse Returns a success response if the child events are generated successfully,
     *                         or an error response if an exception occurs during the process.
     * 
     * @throws \Exception If an error occurs while handling the recurrence, it is caught and returned in the error response.
     */
    public function handleMonthDays(EventRecurring $parent): ApiResponse
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
            return ApiResponse::success('Month days recurrence handled successfully');
        } catch (Exception $e) {
            return ApiResponse::error('Error handling month days recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Handles the generation of recurring child events based on specific days of the week.
     * 
     * This method processes a parent recurring event and creates child events on specified
     * days of the week, within a defined period of recurrence.
     * 
     * @param EventRecurring $parent The parent recurring event that contains the configuration for the recurrence.
     * 
     * @return ApiResponse Returns a success response if the child events are generated successfully,
     *                         or an error response if an exception occurs during the process.
     * 
     * @throws \Exception If an error occurs while handling the recurrence, it is caught and returned in the error response.
     */
    public function handleWeekdays(EventRecurring $parent): ApiResponse
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
            return ApiResponse::success('Weekdays recurrence handled successfully');
        } catch (Exception $e) {
            return ApiResponse::error('Error handling weekdays recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Handles the generation of recurring child events based on specific dates in a period.
     * 
     * This method processes a parent recurring event and creates child events for each specified date
     * within the recurrence period defined by the parent event.
     * 
     * @param EventRecurring $parent The parent recurring event that defines the period and the dates for recurrence.
     * 
     * @return ApiResponse Returns a success response if the child events are generated successfully,
     *                         or an error response if an exception occurs during the process.
     * 
     * @throws \Exception If an error occurs while handling the recurrence, it is caught and returned in the error response.
     */
    public function handlePeriodDates(EventRecurring $parent): ApiResponse
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
            return ApiResponse::success('Period dates recurrence handled successfully');
        } catch (Exception $e) {
            return ApiResponse::error('Error handling period dates recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Handles the generation of recurring child events for everyday recurrence.
     * 
     * This method processes a parent recurring event and creates child events for each day
     * within the recurrence period defined by the parent event.
     * 
     * @param EventRecurring $parent The parent recurring event that defines the period for recurrence.
     * 
     * @return ApiResponse Returns a success response if the child events are generated successfully,
     *                         or an error response if an exception occurs during the process.
     * 
     * @throws \Exception If an error occurs while handling the recurrence, it is caught and returned in the error response.
     */
    public function handleEveryday(EventRecurring $parent): ApiResponse
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
            return ApiResponse::success('Everyday recurrence handled successfully');
        } catch (Exception $e) {
            return ApiResponse::error('Error handling everyday recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Checks if a given date is within the target period defined by the earliest and latest creation dates.
     *
     * @param DateTimeImmutable $dueDate The date to check if it falls within the target period.
     * @param DateTimeImmutable $earliestCreationDate The earliest creation date for the target period.
     * @param DateTimeImmutable $latestCreationDate The latest creation date for the target period.
     *
     * @return bool Returns true if the due date is within the target period, false otherwise.
     */
    public function isWithinTargetPeriod(DateTimeImmutable $dueDate, DateTimeImmutable $earliestCreationDate, DateTimeImmutable $latestCreationDate): bool
    {
        return $dueDate >= $earliestCreationDate && $dueDate <= $latestCreationDate;
    }

    /**
     * Calculates the limits of the period for creating child events.
     *
     * This method calculates the earliest and latest dates for creating child events based on the
     * active day start and end configuration values, as well as the period start and end dates of the parent event.
     *
     * @param EventRecurring $parent The parent recurring event for which the period limits are to be calculated.
     *
     * @return array|ApiResponse Returns an array containing the earliest and latest creation dates if successful,
     *                               or an error response if an exception occurs during the process.
     */
    private function calculatePeriodLimits(EventRecurring $parent): array|ApiResponse
    {
        try {
            $latestDate = (new DateTimeImmutable($this->now->format('Y-m-d')))->modify("+{$this->activeDayEnd} day");
            $latestCreationDate = min($latestDate, $parent->getPeriodeEnd());

            $earliestCreationDate = max($this->now, $parent->getPeriodeStart());
            $earliestCreationDate = new DateTimeImmutable($earliestCreationDate->format('Y-m-d'));

            return [$earliestCreationDate, $latestCreationDate];
        } catch (Exception $e) {
            return ApiResponse::error('Error calculating period limits: ' . $e->getMessage());
        }
    }


}