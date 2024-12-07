<?php
namespace App\Service\Order;

use App\Entity\Supplier\Category;
use App\Entity\Supplier\DeliveryDay;
use App\Entity\Supplier\OrderDay;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Supplier\Supplier;
use App\Entity\User\Contact;
use App\Repository\Supplier\SupplierRepository;
use App\Service\Event\EventRecurringService;
use App\Service\Event\EventService;
use App\Service\User\BusinessService;
use App\Service\User\ContactService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class SupplierService
{
    private DateTimeImmutable $now;
    function __construct(
        private EntityManagerInterface $em,
        private SupplierRepository $supplierRepository,
        private ValidatorService $validateService,
        protected SerializerInterface $serializer,
        protected BusinessService $businessService,
        protected EventRecurringService $eventRecurringService,
        protected EventService $eventService,
        protected ContactService $contactService

    ) {
        $this->now = new DateTimeImmutable('today');
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Retrieves a supplier by its ID.
     *
     * This method fetches a supplier from the database using its ID.
     * If the supplier is not found, it returns an error response.
     *
     * @param int $id The ID of the supplier to retrieve.
     *
     * @return ApiResponse Returns a success response with the supplier data
     *                     or an error response if the supplier is not found.
     */
    public function getSupplier(int $id): ApiResponse
    {
        $supplier = $this->supplierRepository->find($id);
        if (!$supplier) {
            return ApiResponse::error('Supplier not found', [], Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success('Supplier found', ['supplier' => $supplier], Response::HTTP_OK);
    }


    //! ----------------------------------------------------------------------------------------

    /**
     * Retrieves all suppliers.
     *
     * This method fetches all suppliers from the database.
     * If no suppliers are found, it returns an error response.
     *
     * @return ApiResponse Returns a success response with the list of suppliers
     *                     or an error response if no suppliers are found.
     */
    public function getSuppliers(): ApiResponse
    {
        $suppliers = $this->supplierRepository->findAll();
        if (!$suppliers) {
            return ApiResponse::error('Suppliers not found', [], Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success('Suppliers found', ['suppliers' => $suppliers], Response::HTTP_OK);
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Creates a new supplier and its associated data.
     *
     * This method handles the creation of a supplier, including its associated 
     * business, categories, order days, delivery days, and recurring events. 
     * All operations are wrapped in a transaction to ensure data integrity.
     *
     * @param Request $request The HTTP request containing supplier data in JSON format.
     *
     * @return ApiResponse Returns a success response with the created supplier 
     *                     or an error response if any part of the process fails.
     *
     * @throws Exception If an error occurs during the transaction or data persistence.
     */
    public function createSupplier(Request $request): ApiResponse
    {
        $this->em->beginTransaction();
        try {
            $responseData = $this->validateService->validateJson($request);
            if (!$responseData->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseData->getMessage(), null, $responseData->getStatusCode());
            }

            $businessResponse = $this->businessService->createBusiness($request);
            if (!$businessResponse->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($businessResponse->getMessage(), null, $businessResponse->getStatusCode());
            }

            $supplier = (new Supplier())
                ->setLogistic($responseData->getData()[ 'logistic' ])
                ->setHabits($responseData->getData()[ 'habits' ])
                ->setGoodToKnow($responseData->getData()[ 'goodToKnow' ])
                ->setBusiness($businessResponse->getData()[ 'business' ]);

            $this->handleSupplierRelationsOnCreation($supplier, $responseData);

            $this->em->persist($supplier);


            $responseValidation = $this->validateService->validateEntity($supplier);
            if (!$responseValidation->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseValidation->getMessage(), null, $responseValidation->getStatusCode());
            }

            $responseEvent = $this->handleEventCreationsforNewSupplier($supplier, $responseData->getData());
            if (!$responseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseEvent->getMessage(), null, $responseEvent->getStatusCode());
            }

            $this->em->commit();
            $this->em->flush();

            return ApiResponse::success('Supplier created', ['supplier' => $supplier], Response::HTTP_CREATED);


        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    //! ----------------------------------------------------------------------------------------

    public function updateSupplier(int $id, Request $request): ApiResponse
    {
        $this->em->beginTransaction();
        try {
            $supplier = $this->supplierRepository->find($id);
            if (!$supplier) {
                $this->em->rollback();
                return ApiResponse::error("There is no supplier with this id", null, Response::HTTP_BAD_REQUEST);
            }

            $data = $this->validateService->validateJson($request);
            if (!$data->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($data->getMessage(), null, Response::HTTP_BAD_REQUEST);
            }

            $supplier = $this->serializer->deserialize($request->getContent(), Supplier::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $supplier]);

            if (!$supplier) {
                $this->em->rollback();
                return ApiResponse::error("Error while deserializing supplier", null, Response::HTTP_BAD_REQUEST);
            }

            $ResponseRelations = $this->handleSupplierRelationsOnUpdate($supplier, $data);
            if (!$ResponseRelations->isSuccess()) {
                $this->em->rollback();
                return $ResponseRelations;
            }

            $ResponseEvent = $this->handleEventCreationsforUpdatedSupplier($supplier, $data->getData());
            if (!$ResponseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($ResponseEvent->getMessage(), null, $ResponseEvent->getStatusCode());
            }

            $this->em->commit();
            $this->em->flush();

            return ApiResponse::success("Succesfully updated {$supplier->getBusiness()->getName()}", ["supplier" => $supplier], Response::HTTP_OK);
        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_BAD_REQUEST);
        }

    }

    //! ----------------------------------------------------------------------------------------


    /**
     * Deletes a supplier and its associated data.
     *
     * This method deletes a supplier by its ID, including removing all related 
     * entities, logging the deletion as an event, and handling staff and business 
     * associated with the supplier. All changes are wrapped in a transaction to 
     * ensure consistency.
     *
     * @param int $id The ID of the supplier to delete.
     *
     * @return ApiResponse Returns a success response if the supplier is deleted, 
     *                     or an error response if any part of the process fails.
     *
     * @throws Exception If an error occurs during the transaction or entity removal.
     */
    public function deleteSupplier(int $id): ApiResponse
    {
        $this->em->beginTransaction();
        try {
            $supplier = $this->supplierRepository->find($id);
            if (!$supplier) {
                $this->em->rollback();
                return ApiResponse::error("There is no supplier with this id", null, Response::HTTP_BAD_REQUEST);
            }


            $responseRelation = $supplier->removeAllRelations();
            if (!$responseRelation) {
                $this->em->rollback();
                return ApiResponse::error("Error while removing relations", null, Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $responseStaff = $this->deleteStaffAndBusiness($supplier);
            if (!$responseStaff->isSuccess()) {
                $this->em->rollback();
                return $responseStaff;
            }

            $ResponseEvent = $this->createEventDeletedSupplier($supplier);
            if (!$ResponseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($ResponseEvent->getMessage(), null, $ResponseEvent->getStatusCode());
            }

            $this->em->remove($supplier);
            $this->em->commit();
            $this->em->flush();

            return ApiResponse::success("Supplier deleted", null, Response::HTTP_OK);

        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
        [$businessName] = $this->getDataEvents($supplier);

        $data = [
            "section"     => "supplier",
            "description" => "Le fournisseur {$businessName->getName()} a été supprimé.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Fournisseur {$businessName->getName()} supprimé",
        ];
        $event = $this->eventService->createOneEvent($data);
        if (!$event) {
            return ApiResponse::error('Event not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Event created', ['event' => $event], Response::HTTP_CREATED);
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Deletes the staff contacts associated with the supplier's business and removes them from the business.
     *
     * @param Supplier $supplier The supplier whose staff and business contacts need to be deleted.
     *
     * @return ApiResponse Returns a success response if all staff contacts are deleted successfully, 
     *                     or an error response if any deletion fails.
     *
     * @throws \Exception If an error occurs during the deletion process.
     */
    public function deleteStaffAndBusiness($supplier): ApiResponse
    {
        $business = $supplier->getBusiness();
        $staffs = $this->em->getRepository(Contact::class)->findBy(['business' => $business]);
        foreach ($staffs as $staff) {
            $business->removeContact($staff);
            $response = $this->contactService->deleteContact($staff->getId());
            if (!$response->isSuccess()) {
                return $response;
            }
        }
        return ApiResponse::success('Staffs deleted', null, Response::HTTP_OK);
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Create a new category based on the provided JSON request.
     *
     * @param Request $request The HTTP request containing the category data in JSON format.
     *
     * @return ApiResponse Returns a success response with the created category or an error response if validation fails.
     *
     * @throws \Exception If an error occurs during the creation process.
     */
    public function createCategory(Request $request): ApiResponse
    {
        $responseData = $this->validateService->validateJson($request);
        if (!$responseData->isSuccess()) {
            $this->em->rollback();
            return ApiResponse::error($responseData->getMessage(), null, $responseData->getStatusCode());
        }
        $category = new Category();
        $category->setName($responseData->getData()[ 'name' ]);

        $responseValidation = $this->validateService->validateEntity($category);
        if (!$responseValidation->isSuccess()) {
            $this->em->rollback();
            return ApiResponse::error($responseValidation->getMessage(), null, $responseValidation->getStatusCode());
        }
        $this->em->persist($category);
        $this->em->flush();
        return ApiResponse::success('Category created', ['category' => $category], Response::HTTP_CREATED);
    }

    //! ----------------------------------------------------------------------------------------
    /**
     * Determine the type of recurrence based on the content.
     *
     * @param array $content The array containing recurrence information.
     *
     * @return string The key representing the recurrence type.
     *
     * @throws InvalidArgumentException If none or more than one recurrence type is set.
     */
    private function getRecurrenceType(array $content): string
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
    private function handleEventCreationsforNewSupplier(Supplier $supplier, array $content): ApiResponse
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
     * Handle the addition of relations for a supplier.
     *
     * This method associates the supplier with order days, delivery days, and categories.
     * It retrieves the related entities from the database using the provided data and links them to the supplier.
     *
     * @param Supplier   $supplier    The supplier entity to which the relations will be added.
     * @param ApiResponse $responseData The response data containing the order days, delivery days, and categories to associate with the supplier.
     *
     * @return void
     */
    private function handleSupplierRelationsOnCreation(Supplier $supplier, ApiResponse $responseData)
    {
        $orderDays = $responseData->getData()[ 'orderDays' ];
        foreach ($orderDays as $orderDay) {
            $orderDay = $this->em->getRepository(OrderDay::class)->findOneBy(['day' => $orderDay]);
            $supplier->addOrderDay($orderDay);
        }

        $deliveryDays = $responseData->getData()[ 'deliveryDays' ];
        foreach ($deliveryDays as $deliveryDay) {
            $deliveryDay = $this->em->getRepository(DeliveryDay::class)->findOneBy(['day' => $deliveryDay]);
            $supplier->addDeliveryDay($deliveryDay);
        }

        $supplierCategories = $responseData->getData()[ 'categories' ];
        foreach ($supplierCategories as $supplierCategory) {
            $category = $this->em->getRepository(Category::class)->findOneBy(['name' => $supplierCategory]);
            $supplier->addCategory($category);
        }
    }

    //! ----------------------------------------------------------------------------------------

    private function handleEventCreationsforUpdatedSupplier(Supplier $supplier, array $content): ApiResponse
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

    private function handleSupplierRelationsOnUpdate(Supplier $supplier, ApiResponse $responseData): ApiResponse
    {

        try {
            $supplier->removeAllOrderDays();
            $supplier->removeAllDeliveryDays();
            $supplier->removeAllCategories();

            $this->handleSupplierRelationsOnCreation($supplier, $responseData);

        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success("Relations updated", null, Response::HTTP_OK);
    }

    //! ----------------------------------------------------------------------------------------




}



