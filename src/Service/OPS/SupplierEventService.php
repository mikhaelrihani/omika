<?php
namespace App\Service\OPS;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Supplier\Supplier;
use App\Service\Event\EventRecurringService;
use App\Service\Event\EventService;
use App\Utils\ApiResponse;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;


class SupplierEventService
{
    private DateTimeImmutable $now;
    function __construct(
        private EntityManagerInterface $em,
        protected EventRecurringService $eventRecurringService,
        protected EventService $eventService,
    ) {
        $this->now = new DateTimeImmutable('today');
    }

    //! ----------------------------------------------------------------------------------------


    /**
     * Creates a recurring event for a supplier.
     *
     * This method creates a recurring event for a supplier based on the provided data.
     * The event is created with the necessary information and linked to the supplier.
     * Event children are also created based on the recurrence data.
     *
     * @param Supplier $supplier The supplier entity for which the event is created.
     * @param array $content An associative array containing the recurrence data.
     *
     * @return ApiResponse Returns a success response with the created event or an error response on failure.
     */
    private function createEventRecuring(Supplier $supplier, array $content): ApiResponse
    {
        [$businessName, $habits, $goodToKnow, $logistic, $categories, $date] = $this->getDataEvents($supplier);

        $recurrenceType = $this->getRecurrenceType($content);

        if (!$recurrenceType) {
            return ApiResponse::error('Recurrence type not found', [], Response::HTTP_BAD_REQUEST);
        }

        $data = [
            "section"       => "supplier",
            "description"   => "Mettre à jour la commande du fournisseur {$businessName->getName()} et passer la commande.
                            Nos habitudes : {$habits}, Logistique : {$logistic}, Bon à savoir : {$goodToKnow}",
            "type"          => "task",
            "side"          => "office",
            "title"         => "Commande - {$businessName->getName()} - {$date}",
            $recurrenceType => $content[$recurrenceType],
        ];

        $eventRecurring = $this->eventRecurringService->createOneEventRecurringParent($data);
        if (!$eventRecurring) {
            return ApiResponse::error('Event recurring not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $eventRecurring = $eventRecurring->getData()[ "eventRecurringParent" ];

        $childrensResponse = $this->eventRecurringService->createChildrenWithTag($eventRecurring);
        if (!$childrensResponse) {
            return ApiResponse::error('Event recurring children not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return ApiResponse::success('Event recurring created with its childrens', ['eventRecurring' => $eventRecurring], Response::HTTP_CREATED);
    }


    //! ----------------------------------------------------------------------------------------

    /**
     * Handle the creation of events associated with a supplier.
     *
     * This method manages the creation of a recurring event and a new supplier event.
     * If any of the event creation processes fail, the transaction is rolled back, 
     * and an error response is returned.
     *
     * @param Supplier $supplier The supplier entity for which the events are created.
     * @param array $content An associative array containing the necessary data for creating the recurring event.
     *
     * @return ApiResponse Returns a success response if both events are created successfully.
     *                     Returns an error response if any event creation fails.
     *
     * @throws \Exception If an unexpected error occurs during the process.
     */
    public function handleEventCreationsforNewSupplier(Supplier $supplier, array $content): ApiResponse
    {
        $responseEvent = $this->createEventRecuring($supplier, $content);
        if (!$responseEvent->isSuccess()) {
            $this->em->rollback();
            return ApiResponse::error($responseEvent->getMessage(), null, $responseEvent->getStatusCode());
        }

        $supplier->setRecurringEvent($responseEvent->getData()[ 'eventRecurring' ]);


        $ResponseEvent = $this->createEventNewSupplier($supplier);
        if (!$ResponseEvent->isSuccess()) {
            $this->em->rollback();
            return ApiResponse::error($ResponseEvent->getMessage(), null, $ResponseEvent->getStatusCode());
        }
        return ApiResponse::success($ResponseEvent->getMessage(), null, Response::HTTP_CREATED);
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Creates a new event for a newly added supplier.
     *
     * @param Supplier $supplier The supplier entity for which the event is created.
     *
     * @return ApiResponse Returns a success response with the created event or an error response on failure.
     *
     * @throws \Exception If the event creation service encounters an error.
     */
    private function createEventNewSupplier(Supplier $supplier): ApiResponse
    {
        [$businessName, $habits, $goodToKnow, $logistic, $categories, $date] = $this->getDataEvents($supplier);

        $data = [
            "section"     => "supplier",
            "description" => "Un nouveau fournisseur {$businessName->getName()} a été ajouté .
                            {$businessName->getName()} est un fournisseur de : {$categories}.
                            Nos habitudes : {$habits}, Logistique : {$logistic}, Bon à savoir : {$goodToKnow}.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Nouveau fournisseur {$businessName->getName()}.",
            "dueDate"     => $date
        ];
        $event = $this->eventService->createOneEvent($data);
        if (!$event) {
            return ApiResponse::error('Event not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Event created', ['event' => $event], Response::HTTP_CREATED);
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Handle the creation and update of events related to an updated supplier.
     *
     * This method removes an existing recurring event from the supplier, deletes the old recurring event,
     * creates a new recurring event based on the given content, and finally creates an event to track the
     * supplier update.
     *
     * @param Supplier $supplier The supplier entity being updated.
     * @param array    $content  The content data used for creating the new recurring event.
     *
     * @return ApiResponse Returns an ApiResponse indicating success or failure.
     *
     * @throws InvalidArgumentException If the supplier or event data is invalid.
     *
     * Possible response codes:
     * - 201 (HTTP_CREATED): When the events are successfully created.
     * - 400 (HTTP_BAD_REQUEST): When the recurring event is not found.
     * - 500 (HTTP_INTERNAL_SERVER_ERROR): When event creation or deletion fails.
     */
    public function handleEventCreationsforUpdatedSupplier(Supplier $supplier, array $content): ApiResponse
    {
        $recuringEvent = $supplier->getRecurringEvent();
        if (!$recuringEvent) {
            return ApiResponse::error('Recurring event not found', [], Response::HTTP_BAD_REQUEST);
        }
        $supplier->setRecurringEvent(null);
        $responseEvent = $this->eventRecurringService->deleteRecurringEvent($recuringEvent);
        if (!$responseEvent->isSuccess()) {
            return ApiResponse::error($responseEvent->getMessage(), null, $responseEvent->getStatusCode());
        }

        $responseEvent = $this->createEventRecuring($supplier, $content);
        if (!$responseEvent->isSuccess()) {
            return ApiResponse::error($responseEvent->getMessage(), null, $responseEvent->getStatusCode());
        }

        $supplier->setRecurringEvent($responseEvent->getData()[ 'eventRecurring' ]);


        $ResponseEvent = $this->createEventUpdatedSupplier($supplier);
        if (!$ResponseEvent->isSuccess()) {
            return ApiResponse::error($ResponseEvent->getMessage(), null, $ResponseEvent->getStatusCode());
        }
        return ApiResponse::success($ResponseEvent->getMessage(), null, Response::HTTP_CREATED);

    }
    //! ----------------------------------------------------------------------------------------

    /**
     * Create a new event for an updated supplier.
     *
     * @param Supplier $supplier The supplier entity for which the event is created.
     *
     * @return ApiResponse Returns a success response with the created event or an error response on failure.
     */
    private function createEventUpdatedSupplier(Supplier $supplier): ApiResponse
    {
        [$businessName, $habits, $goodToKnow, $logistic, $categories, $date] = $this->getDataEvents($supplier);

        $data = [
            "section"     => "supplier",
            "description" => "Le fournisseur {$businessName->getName()} a été mise a jour .
                            {$businessName->getName()} est un fournisseur de : {$categories}.
                            Nos habitudes : {$habits}, Logistique : {$logistic}, Bon à savoir : {$goodToKnow}.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Mise a jour du fournisseur {$businessName->getName()}.",
            "dueDate"     => $date
        ];
        $event = $this->eventService->createOneEvent($data);
        if (!$event) {
            return ApiResponse::error('Event not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Event created', ['event' => $event], Response::HTTP_CREATED);
    }


    //! ----------------------------------------------------------------------------------------

    /**
     * Creates an event to log the deletion of a supplier.
     *
     * @param Supplier $supplier The supplier that has been deleted.
     *
     * @return ApiResponse Returns a success response with the created event data 
     *                     or an error response if the event creation fails.
     */
    public function createEventDeletedSupplier($supplier): ApiResponse
    {
        [$businessName, $habits, $goodToKnow, $logistic, $categories, $date] = $this->getDataEvents($supplier);

        $data = [
            "section"     => "supplier",
            "description" => "Le fournisseur {$businessName->getName()} a été supprimé.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Fournisseur {$businessName->getName()} supprimé",
            "dueDate"     => $date
        ];
        $event = $this->eventService->createOneEvent($data);
        if (!$event) {
            return ApiResponse::error('Event not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Event created', ['event' => $event], Response::HTTP_CREATED);
    }
    //! ----------------------------------------------------------------------------------------

    /**
     * Collects and returns data related to the supplier for event creation.
     *
     * @param Supplier $supplier The supplier entity from which to extract data.
     *
     * @return array Returns an array containing the following elements:
     *               - string $businessName: The business name of the supplier.
     *               - string|null $habits: The habits associated with the supplier.
     *               - string|null $goodToKnow: Additional information about the supplier.
     *               - string|null $logistic: Logistic details of the supplier.
     *               - string $categories: A comma-separated string of category names.
     *               - string $date: The current date in 'Y-m-d' format.
     */
    private function getDataEvents(Supplier $supplier): array
    {
        $businessName = $supplier->getBusiness();
        $habits = $supplier->getHabits();
        $goodToKnow = $supplier->getGoodToKnow();
        $logistic = $supplier->getLogistic();
        $date = $this->now->format('Y-m-d');
        $categories = $supplier->getCategories();
        $categories = $categories->map(fn($category) => $category->getName())->toArray();
        $categories = empty($categories) ? 'unknown' : implode(", ", $categories);

        return [$businessName, $habits, $goodToKnow, $logistic, $categories, $date];
    }

    //! ------------------------------------------------------------------------------------
    /**
     * Determine the type of recurrence based on the content.
     *
     * @param array $content The array containing recurrence information.
     *
     * @return string The key representing the recurrence type.
     *
     * @throws InvalidArgumentException If none or more than one recurrence type is set.
     */
    public function getRecurrenceType(array $content): string
    {
        $values = [
            "periodDates" => !empty($content[ 'periodDates' ]),
            "isEveryday"  => !empty($content[ 'isEveryday' ]),
            "weekDays"    => !empty($content[ 'weekDays' ]),
            "monthDays"   => !empty($content[ 'monthDays' ]),
        ];

        // Filtrer pour ne garder que les valeurs à `true`
        $recurrenceType = array_filter($values);

        // Vérifier qu'une seule valeur est `true`
        if (count($recurrenceType) !== 1) {
            throw new InvalidArgumentException(
                "Exactly one of 'periodDates', 'isEveryday', 'weekDays', or 'monthDays' must be set."
            );
        }

        // Retourner la clé correspondant au type de récurrence
        return array_key_first($recurrenceType);
    }



}



