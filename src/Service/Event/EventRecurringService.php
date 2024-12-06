<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventRecurring;
use App\Entity\Event\MonthDay;
use App\Entity\Event\PeriodDate;
use App\Entity\Event\Section;
use App\Entity\Event\WeekDay;
use App\Entity\User\User;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use App\Repository\User\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\Event\TagService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\CurrentUser;
use App\Utils\JsonResponseBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EventRecurringService
{
    protected $now;
    protected $activeDayStart;
    protected $activeDayEnd;
    protected $children;

    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventService $eventService,
        protected TagService $tagService,
        protected CurrentUser $currentUser,
        protected ParameterBagInterface $parameterBag,
        protected ValidatorService $validatorService,
        protected JsonResponseBuilder $jsonResponseBuilder,
        protected UserRepository $userRepository
    ) {
        $this->now = new DateTimeImmutable('today');
        $this->activeDayStart = $this->parameterBag->get('activeDayStart');
        $this->activeDayEnd = $this->parameterBag->get('activeDayEnd');
        $this->children = [];
    }


    //! --------------------------------------------------------------------------------------------
    /**
     * Creates a single recurring event parent entity based on provided data.
     *
     * This method handles the creation of a recurring event parent (`EventRecurring`) and associates 
     * it with the appropriate recurrence type, users, and other related entities.
     *
     * @param array $data The data required to create the recurring event. Expected keys:
     *     - 'periodeStart': DateTimeImmutable, the start date of the recurrence period.
     *     - 'periodeEnd': DateTimeImmutable, the end date of the recurrence period.
     *     - 'isEveryday': bool, (optional) indicates if the event recurs every day.
     *     - 'monthDays': array, (optional) days of the month for recurrence.
     *     - 'weekDays': array, (optional) days of the week for recurrence.
     *     - 'periodDates': array, (optional) specific dates for recurrence.
     *     - 'usersId': array, IDs of users to share the event with.
     *     - 'section': string, name of the section associated with the event.
     *     - 'description': string, description of the event.
     *     - 'side': string, side associated with the event.
     *     - 'title': string, title of the event.
     *     - 'type': string, type of the event.
     *
     * @return ApiResponse An ApiResponse object indicating success or error:
     *     - On success, the response contains the created `EventRecurring` entity and an HTTP 201 Created status.
     *     - On failure, the response contains an error message and an appropriate HTTP status code.
     *
     * @throws Exception If an error occurs during the creation process.
     *
     * ### Workflow
     * 1. Determines the recurrence type (`isEveryday`, `monthDays`, `weekDays`, `periodDates`).
     * 2. Validates the section by finding it in the repository.
     * 3. Creates a new `EventRecurring` entity and sets its properties.
     * 4. Calls `addRecurringParentsRelations` to associate recurrence data.
     * 5. Associates the event with users by calling `addSharedWith`.
     * 6. Validates the entity using `validatorService`.
     * 7. Persists the entity and flushes the data to the database.
     *
     * ### Error Scenarios
     * - Invalid or missing recurrence type returns an error response.
     * - Section not found returns an error response.
     * - Validation failures return an error response.
     * - General exceptions are caught and returned as an error response.
     */

    public function createOneEventRecurringParent(array $data): ApiResponse
    {
        try {
            $currentUser = $this->currentUser->getCurrentUser();

            // cela nous permet de creer automatiquement des events recurrents pour tous les users.
            $allUsers = $this->userRepository->findAll();
            $allUsersId = array_map(fn($user) => $user->getId(), $allUsers);
            $usersId = isset($data[ 'usersId' ]) ?? $allUsersId;

            $users = $this->em->getRepository(User::class)->findBy(['id' => $usersId]);
            if (isset($data['usersId']) && count($users) !== count($data[ 'usersId' ])) {
                throw new InvalidArgumentException('Some user IDs could not be found.');
            }

            $recurrenceType = match (true) {
                !empty($data[ 'isEveryday' ]) => "isEveryday",
                !empty($data[ 'monthDays' ]) => "monthDays",
                !empty($data[ 'weekDays' ]) => "weekDays",
                !empty($data[ 'periodDates' ]) => "periodDates",
                default => null
            };

            if ($recurrenceType === null) {
                return ApiResponse::error('Invalid recurrence type', null, Response::HTTP_BAD_REQUEST);
            }

            $section = $this->em->getRepository(Section::class)->findOneBy(["name" => $data[ "section" ]]);
            if (!$section) {
                return ApiResponse::error('Section not found', null, Response::HTTP_NOT_FOUND);
            }

            $eventRecurring = new EventRecurring();
            $eventRecurring
                ->setPeriodeStart(isset($data[ "periodeStart" ]) ? new DateTimeImmutable($data[ "periodeStart" ]) : $this->now)
                ->setPeriodeEnd(isset($data[ "periodeEnd" ]) ? new DateTimeImmutable($data[ "periodeEnd" ]) : null)
                ->setCreatedBy($currentUser->getFullName())
                ->setUpdatedBy($currentUser->getFullName())
                ->setSection($section)
                ->setDescription($data[ "description" ])
                ->setSide($data[ "side" ])
                ->setTitle($data[ "title" ])
                ->setType($data[ "type" ]);


            $response = $this->addRecurringParentsRelations($eventRecurring, $recurrenceType, $data[$recurrenceType]);
            if (!$response->isSuccess()) {
                return $response;
            }

            foreach ($users as $user) {
                $eventRecurring->addSharedWith($user);
            }

            $validator = $this->validatorService->validateEntity($eventRecurring);
            if (!$validator->isSuccess()) {
                return $validator;
            }

            $alreadyExist = $this->doesEventRecurringAlreadyExist($eventRecurring);
            if ($alreadyExist) {
                return ApiResponse::error('EventRecurring already exists', null, Response::HTTP_CONFLICT);
            }
            $this->em->persist($eventRecurring);
            $this->em->flush();

            return ApiResponse::success('EventRecurring parent created successfully.', ["eventRecurringParent" => $eventRecurring], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while creating eventRecurring parent: ' . $e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Adds parent relations to a recurring event based on the recurrence type and provided data.
     *
     * This method updates the given `EventRecurring` entity by associating it with parent relations 
     * (such as `MonthDay`, `WeekDay`, or `PeriodDate`) depending on the provided recurrence type and data.
     *
     * @param EventRecurring $eventRecurring The recurring event entity to update.
     * @param string $recurrenceType The type of recurrence. Expected values:
     *     - "1" for "monthDays"
     *     - "2" for "weekDays"
     *     - "3" for "periodDates"
     *     - "4" for "isEveryday".
     * @param array $recurrenceData The data used to create the parent relations (e.g., days, dates).
     *
     * @return ApiResponse An ApiResponse object indicating success or error:
     *     - On success, the response contains a success message and an HTTP 201 Created status.
     *     - On failure, the response contains an error message with the appropriate HTTP status code.
     *
     * @throws Exception If an error occurs while processing the recurrence data.
     *
     * ### Behavior
     * - Based on the `$recurrenceType`:
     *     - Case 1: Adds `MonthDay` entities with the specified days to the recurring event.
     *     - Case 2: Adds `WeekDay` entities with the specified days to the recurring event.
     *     - Case 3: Adds `PeriodDate` entities with the specified dates to the recurring event.
     *     - Case 4: Marks the event as occurring "everyday".
     * - If an invalid recurrence type is provided, an error response is returned.
     * - If an exception occurs during processing, an error response with HTTP 500 status is returned.
     */
    private function addRecurringParentsRelations(EventRecurring $eventRecurring, string $recurrenceType, array $recurrenceData, ): ApiResponse
    {
        try {
            switch ($recurrenceType) {

                case "monthDays":
                    foreach ($recurrenceData as $day) {
                        $monthDay = new MonthDay();
                        $monthDay->setDay($day);
                        $eventRecurring->addMonthDay($monthDay);
                    }
                    break;

                case "weekDays":
                    foreach ($recurrenceData as $day) {
                        $weekDay = new WeekDay();
                        $weekDay->setDay($day);
                        $eventRecurring->addWeekDay($weekDay);
                    }
                    break;

                case "periodDates":
                    foreach ($recurrenceData as $date) {
                        $periodDate = new PeriodDate();
                        $periodDate->setDate($date);
                        $eventRecurring->addPeriodDate($periodDate);
                    }
                    break;

                case "isEveryday":
                    $eventRecurring->setEveryday(true);
                    break;

                default:
                    throw new Exception('Invalid recurrence type');
            }
            $eventRecurring->setRecurrenceType($recurrenceType);
            return ApiResponse::success('Recurring event parent relations set successfully.', [], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while setting recurring event parent relations: ' . $e->getMessage(), [], Response::HTTP_INTERNAL_SERVER_ERROR);

        }
    }

    //! --------------------------------------------------------------------------------------------

    public function handlePreDeleteRecurringEventParent(EventRecurring $eventRecurring): ApiResponse
    {
        $this->em->beginTransaction(); // Démarrage d'une transaction explicite

        try {
            $childrens = $eventRecurring->getEvents();

            foreach ($childrens as $child) {
                // Ignorer les événements passés
                if ($child->getDueDate() < $this->now) {
                    continue;
                }

                if ($child->getType() === 'task') {
                    $task = $child->getTask();
                    if ($task->getTaskStatus() === "todo") {
                        $users = $task->getSharedWith();
                        $response = $this->eventService->removeEventAndUpdateTagCounters($child);

                        if (!$response->isSuccess()) {
                            $this->em->rollback();
                            return $response;
                        }
                    } else {
                        $task->setTaskStatus("warning");
                    }
                } elseif ($child->getType() === 'info') {
                    $shareWith = $child->getInfo()->getSharedWith();
                    $users = new ArrayCollection();

                    foreach ($shareWith as $userInfo) {
                        $users->add($userInfo->getUser());
                    }

                    $response = $this->eventService->removeEventAndUpdateTagCounters($child);

                    if (!$response->isSuccess()) {
                        $this->em->rollback();
                        return $response;
                    }
                } else {
                    // Ignorer les événements de type inconnu
                    continue;
                }
            }

            $this->em->flush();
            $this->em->commit(); // Valider la transaction

            return ApiResponse::success(
                'On pre delete, Recurring event children processed successfully',
                null,
                Response::HTTP_OK
            );

        } catch (Exception $e) {
            // Effectuer un rollback uniquement si une transaction est active
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }

            return ApiResponse::error(
                'An error occurred while processing recurring event children: ' . $e->getMessage(),
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    //! --------------------------------------------------------------------------------------------

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
            $children = $eventRecurring->getEvents();
            foreach ($children as $child) {
                $this->eventService->removeEventAndUpdateTagCounters($child);
            }
            $this->em->remove($eventRecurring);
            $this->em->flush();
            return ApiResponse::success('Recurring event deleted successfully');

        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while deleting recurring event: ' . $e->getMessage());
        }
    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Creates child events for a recurring event and tags them.
     *
     * This method processes the given recurring event and creates child events based on the recurrence type
     * and configuration of the event. The child events are then tagged and persisted to the database.
     *
     * @param EventRecurring $eventRecurring The recurring event for which child events are to be created.
     * @param bool $cronJob A flag indicating if the method is called from a cron job.
     *
     * @return Collection A collection of the created child events.
     */
    public function createChildrenWithTag(EventRecurring $eventRecurring, bool $cronJob = false): Collection
    {
        $this->children = [];
        $this->handleRecurrenceType($eventRecurring, $cronJob);
        foreach ($this->children as $child) {
            $this->tagService->createTag($child);
        }
        return new ArrayCollection($this->children);
    }

    //! --------------------------------------------------------------------------------------------

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
        $this->children[] = $child;
    }

    //! --------------------------------------------------------------------------------------------

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

    //! --------------------------------------------------------------------------------------------

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

    //! --------------------------------------------------------------------------------------------

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
                throw new InvalidArgumentException('Invalid recurrence type');
        }

    }
    //! --------------------------------------------------------------------------------------------

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

    //! --------------------------------------------------------------------------------------------
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

    //! --------------------------------------------------------------------------------------------
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

    //! --------------------------------------------------------------------------------------------
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
    //! --------------------------------------------------------------------------------------------
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

    //! --------------------------------------------------------------------------------------------
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

    //! --------------------------------------------------------------------------------------------

    /**
     * Creates a recurring event along with its associated children and related tags.
     *
     * This method first attempts to create a recurring event parent. If successful, it creates the associated
     * children events and tags. It then returns a JSON response with the success message and the created entities.
     * 
     * If any part of the process fails, an appropriate error message is returned.
     *
     * @param ApiResponse $response The API response object containing the data required for creating the event.
     *                              This will be processed to create the recurring event and its associated entities.
     *
     * @return JsonResponse A JSON response containing the result of the event creation process. 
     *                      This could include a success message, event data, or an error message in case of failure.
     * 
     * @throws \Exception If an error occurs during the event creation process, such as a database or validation issue.
     */
    public function createOneEventRecurringParentWithChildrenAndTags(ApiResponse $response): JsonResponse
    {
        $response = $this->createOneEventRecurringParent($response->getData());

        // If parent creation fails, return error message
        if (!$response->isSuccess()) {
            return $this->jsonResponseBuilder->createJsonResponse([$response->getMessage()], $response->getStatusCode());
        }

        // Proceed if the parent creation is successful
        if ($response->getData() !== null) {
            // Extract the event recurring parent data
            $eventRecurringParent = $response->getData()[ "eventRecurringParent" ];

            // Create associated children events and tags
            $events = $this->createChildrenWithTag($eventRecurringParent);

            // Prepare the success response with created events and parent data
            $response = ApiResponse::success(
                "Event Recurring created successfully with its {$events->count()} children and related Tags.",
                ['eventRecurring' => $eventRecurringParent, "events" => $events],
                Response::HTTP_CREATED
            );

            return $this->jsonResponseBuilder->createJsonResponse([$response->getMessage()], $response->getStatusCode());
        } else {
            // In case of no data or failure, return the error response
            return $this->jsonResponseBuilder->createJsonResponse([$response->getMessage()], $response->getStatusCode());
        }
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Checks if an EventRecurring entity with the same attributes already exists.
     *
     * This method verifies the existence of an EventRecurring entity by comparing
     * the title, creation date, section, and type.
     *
     * @param EventRecurring $eventRecurring The EventRecurring entity to check for duplication.
     *
     * @return bool True if a matching EventRecurring entity exists, false otherwise.
     *
     * @throws \Doctrine\ORM\NonUniqueResultException If the query does not return a single scalar result.
     */

    private function doesEventRecurringAlreadyExist(EventRecurring $eventRecurring): bool
    {
        $query = $this->em->createQuery(
            'SELECT COUNT(e.id)
             FROM App\Entity\Event\EventRecurring e
             WHERE e.title = :title
               AND e.recurrenceType = :recurrenceType
               AND e.section = :section
               AND e.type = :type'
        );

        $query->setParameters([
            'title'          => $eventRecurring->getTitle(),
            'recurrenceType' => $eventRecurring->getRecurrenceType(),
            'section'        => $eventRecurring->getSection(),
            'type'           => $eventRecurring->getType(),
        ]);

        return (bool) $query->getSingleScalarResult();

    }
}