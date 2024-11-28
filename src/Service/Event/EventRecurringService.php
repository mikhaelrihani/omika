<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventRecurring;
use App\Entity\Event\MonthDay;
use App\Entity\Event\PeriodDate;
use App\Entity\Event\Section;
use App\Entity\Event\WeekDay;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\Event\TagService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\CurrentUser;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class EventRecurringService
{
    protected $now;
    protected $activeDayStart;
    protected $activeDayEnd;
    protected $childrens;

    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventService $eventService,
        protected TagService $tagService,
        protected CurrentUser $currentUser,
        protected ParameterBagInterface $parameterBag,
        protected ValidatorService $validatorService
    ) {
        $this->now = new DateTimeImmutable('today');
        $this->activeDayStart = $this->parameterBag->get('activeDayStart');
        $this->activeDayEnd = $this->parameterBag->get('activeDayEnd');
        $this->childrens = [];
    }
    public function createOneEventRecurringParent(array $data): ApiResponse
    {
        try {
            $currentUser = $this->currentUser->getCurrentUser();
            $users = $this->eventService->getUsers($data[ 'usersId' ]);

            $recurrenceType = match (true) {
                !empty($data[ 'isEveryday' ]) => "isEveryday",
                !empty($data[ 'monthDays' ]) => "monthDays",
                !empty($data[ 'weekDays' ]) => "weekDays",
                !empty($data[ 'periodDates' ]) => "periodDates",
                default => null
            };

            if ($recurrenceType === null) {
                return ApiResponse::error('Invalid recurrence type');
            }

            $section = $this->em->getRepository(Section::class)->findOneBy(["name" => $data[ "section" ]]);
            if (!$section) {
                return ApiResponse::error('Section not found');
            }

            $eventRecurring = new EventRecurring();
            $eventRecurring
                ->setPeriodeStart($data[ "periodeStart" ])
                ->setPeriodeEnd($data[ "periodeEnd" ])
                ->setCreatedAt($this->now)
                ->setUpdatedAt($this->now)
                ->setCreatedBy($currentUser->getFullName())
                ->setUpdatedBy($currentUser->getFullName())
                ->setSection($section)
                ->setDescription($data[ "description" ])
                ->setSide($data[ "side" ])
                ->setTitle($data[ "title" ])
                ->setType($data[ "type" ]);

            $validator = $this->validatorService->validateEntity($eventRecurring);
            if (!$validator->isSuccess()) {
                return $validator;
            }

            foreach ($users as $user) {
                $eventRecurring->addSharedWith($user);
            }

            $response = $this->addRecurringParentsRelations($eventRecurring, $recurrenceType, $data[$recurrenceType]);
            if (!$response->isSuccess()) {
                return $response;
            }
            $this->em->persist($eventRecurring);
            $this->em->flush();

            return ApiResponse::success('EventRecurring parent created successfully.', ["eventRecurringParent" => $eventRecurring], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while creating eventRecurring parent: ' . $e->getMessage());
        }
    }

    private function addRecurringParentsRelations(EventRecurring $eventRecurring, string $recurrenceType, array $recurrenceData, ): ApiResponse
    {
        try {
            switch ($recurrenceType) {

                case 1:
                    $recurrenceType = "monthDays";
                    foreach ($recurrenceData as $day) {
                        $monthDay = new MonthDay();
                        $monthDay->setDay($day);
                        $eventRecurring->addMonthDay($monthDay);
                    }
                    break;

                case 2:
                    $recurrenceType = "weekDays";
                    foreach ($recurrenceData as $day) {
                        $weekDay = new WeekDay();
                        $weekDay->setDay($day);
                        $eventRecurring->addWeekDay($weekDay);
                    }
                    break;

                case 3:
                    $recurrenceType = "periodDates";
                    foreach ($recurrenceData as $date) {
                        $periodDate = new PeriodDate();
                        $periodDate->setDate($date);
                        $eventRecurring->addPeriodDate($periodDate);
                    }

                    break;

                case 4:
                    $recurrenceType = "isEveryday";
                    $eventRecurring->setEveryday(true);
                    break;

                default:
                    return ApiResponse::error('Invalid recurrence type provided.', [], Response::HTTP_BAD_REQUEST);
            }
            $eventRecurring->setRecurrenceType($recurrenceType);
            return ApiResponse::success('Recurring event parent relations set successfully.', [], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while setting recurring event parent relations: ' . $e->getMessage(), [], Response::HTTP_INTERNAL_SERVER_ERROR);

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
    public function UpdateRecurringEventParent(EventRecurring $eventRecurring): ApiResponse
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
                'An error occurred while updating recurring event children: ' . $e->getMessage()
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
     * Creates child events for a recurring event.
     *
     * This method processes the given recurring event and creates child events based on the recurrence type.
     * The method calls the appropriate handler method based on the recurrence type of the event.
     *
     * @param EventRecurring $eventRecurring The recurring event for which child events are to be created.
     * @param bool $cronJob A flag indicating if the method is called from a cron job.
     *
     * @return Collection A collection of child events created for the recurring event.
     */
    public function createChildrens(EventRecurring $eventRecurring, bool $cronJob = false): Collection
    {
        $this->childrens = [];
        $this->handleRecurrenceType($eventRecurring, $cronJob);
        foreach ($this->childrens as $child) {
            $this->tagService->createTag($child);
        }
        return new ArrayCollection($this->childrens);
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
     */
    private function createOneChild(EventRecurring $parent, DateTimeImmutable $dueDate): void
    {
        if ($this->isAlreadyCreated($parent, $dueDate)) {
            return;
        }
        $child = ($this->setOneChildBase($parent))
            ->setDueDate($dueDate)
            ->setFirstDueDate($dueDate);

        $this->eventService->setTimestamps($child);

        $users = $parent->getSharedWith();
        $this->eventService->setRelations($child, $users);

        $this->em->persist($child);
        $parent->addEvent($child);
        $this->em->flush();
        $this->childrens[] = $child;
    }

    /**
     * Checks if a recurring event has already been created.
     *
     * This method checks if a recurring event with the same title and due date as the given event already exists.
     *
     * @param Event $event The event to check if it has already been created.
     *
     * @return bool Returns true if the event has already been created, false otherwise.
     */
    private function isAlreadyCreated(EventRecurring $parent, DateTimeImmutable $dueDate): bool
    {
        $event = $this->eventRepository->findOneBy(['title' => $parent->getTitle(), 'dueDate' => $dueDate]);
        return $event ? true : false;
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
    private function setOneChildBase(EventRecurring $parent): Event
    {
        $child = (new Event())
            ->setDescription($parent->getDescription())
            ->setIsImportant(false)
            ->setSide($parent->getSide())
            ->setTitle($parent->getTitle())
            ->setCreatedBy($parent->getCreatedBy())
            ->setUpdatedBy($parent->getUpdatedBy())
            ->setType($parent->getType())
            ->setSection($parent->getSection())
            ->setIsProcessed(false)
            ->setPublished(true)
            ->setPending(false)
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
     */
    private function handleRecurrenceType(EventRecurring $parent, bool $cronJob): void
    {

        $recurrenceType = $parent->getRecurrenceType();

        switch ($recurrenceType) {
            case "monthDays":
                $this->handleMonthDays($parent, $cronJob);
                break;
            case "weekDays":
                $this->handleWeekdays($parent, $cronJob);
                break;
            case "periodDates":
                $this->handlePeriodDates($parent, $cronJob);
                break;
            case "isEveryday":
                $this->handleEveryday($parent, $cronJob);
                break;
            default:
                throw new \InvalidArgumentException('Invalid recurrence type');
        }

    }

    /**
     * Handles the generation of recurring child events based on specific days of the month.
     * 
     * This method processes a parent recurring event and creates child events on specified
     * days of the month, within a defined period of recurrence.
     * 
     * @param EventRecurring $parent The parent recurring event that contains the configuration for the recurrence.
     */
    private function handleMonthDays(EventRecurring $parent, bool $cronJob): void
    {

        $monthDays = $parent->getMonthDays();
        [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent, $cronJob);

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

    }

    /**
     * Handles the generation of recurring child events based on specific days of the week.
     * 
     * This method processes a parent recurring event and creates child events on specified
     * days of the week, within a defined period of recurrence.
     * 
     * @param EventRecurring $parent The parent recurring event that contains the configuration for the recurrence.
     */
    private function handleWeekdays(EventRecurring $parent, bool $cronJob): void
    {

        $weekDays = $parent->getWeekDays();
        [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent, $cronJob);

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

    }

    /**
     * Handles the generation of recurring child events based on specific dates in a period.
     * 
     * This method processes a parent recurring event and creates child events for each specified date
     * within the recurrence period defined by the parent event.
     * 
     * @param EventRecurring $parent The parent recurring event that defines the period and the dates for recurrence.
     * 
     */
    private function handlePeriodDates(EventRecurring $parent, bool $cronJob): void
    {
        [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent, $cronJob);
        $dates = $parent->getPeriodDates();

        foreach ($dates as $date) {
            $dueDate = $date->getDate();

            if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                $this->createOneChild($parent, $dueDate);
            }
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
     */
    private function handleEveryday(EventRecurring $parent, bool $cronJob): void
    {

        [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent, $cronJob);
        $diff = (int) $earliestCreationDate->diff($latestCreationDate)->format('%r%a');

        for ($i = 0; $i <= $diff; $i++) {
            $dueDate = $earliestCreationDate->modify("+{$i} day");

            if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                $this->createOneChild($parent, $dueDate);
            }
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
    private function isWithinTargetPeriod(DateTimeImmutable $dueDate, DateTimeImmutable $earliestCreationDate, DateTimeImmutable $latestCreationDate): bool
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
     * @return array Returns an array containing the earliest and latest creation dates if successful,
     *                              
     */
    private function calculatePeriodLimits(EventRecurring $parent, bool $cronJob): array
    {
        // on créee les events children dans la periode aujourdhui a 7 jours a chaque creation d'un eventrecurring parent,
        $latestDate = (new DateTimeImmutable($this->now->format('Y-m-d')))->modify("+{$this->activeDayEnd} day");
        $latestCreationDate = min($latestDate, $parent->getPeriodeEnd());

        $earliestCreationDate = max($this->now, $parent->getPeriodeStart());
        $earliestCreationDate = new DateTimeImmutable($earliestCreationDate->format('Y-m-d'));

        // si nous sommes dans le processus du cronJob, on créee les events children a la date finale de active day range soit activeDayEnd
        if ($cronJob) {
            $earliestCreationDate = $latestDate;
        }

        return [$earliestCreationDate, $latestCreationDate];
    }

}