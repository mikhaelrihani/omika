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

            $this->em->persist($supplier);


            $responseValidation = $this->validateService->validateEntity($supplier);
            if (!$responseValidation->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseValidation->getMessage(), null, $responseValidation->getStatusCode());
            }

            $responseEvent = $this->createEventRecuring($supplier, $responseData->getData());
            if (!$responseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseEvent->getMessage(), null, $responseEvent->getStatusCode());
            }

            $supplier->setRecurringEvent($responseEvent->getData()[ 'eventRecurring' ]->getData()["eventRecurringParent"]);


            $ResponseEvent = $this->createEventNewSupplier($supplier);
            if (!$ResponseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($ResponseEvent->getMessage(), null, $ResponseEvent->getStatusCode());
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

            $exist = $this->isSupplierAlreadyExist($data);
            if (!$exist->isSuccess()) {
                return $exist;
            }

            $supplier = $this->serializer->deserialize($request->getContent(), Supplier::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $supplier]);

            $this->eventRecurringService->deleteRecurringEvent($supplier->getEventRecurring());
            $reponseEvent = $this->createEventRecuring($supplier, $request);
            if (!$reponseEvent->isSuccess()) {
                $this->em->rollback();
                return $reponseEvent;
            }
            $supplier->setRecurringEvent($reponseEvent->getData()[ 'eventRecurring' ]);

            $this->em->persist($supplier);
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

    private function createEventRecuring(Supplier $supplier, array $content): ApiResponse
    {
        [$businessName, $habits, $goodToKnow, $logistic, $date] = $this->getDataEvents($supplier);

        $data = [
            "section"     => "supplier",
            "description" => "Mettre à jour la commande du fournisseur {$businessName->getName()} et passer la commande.
                            Nos habitudes : {$habits}, Logistique : {$logistic}, Bon à savoir : {$goodToKnow}",
            "type"        => "task",
            "side"        => "office",
            "title"       => "Commande {$businessName->getName()} du {$date}",
            "periodDates" => $content[ 'periodDates' ],
            "isEveryday" => $content[ 'isEveryday'],
            "weekDays" => $content[ 'weekDays' ],
            "monthDays" => $content[ 'monthDays' ],
          
        ];
   
        $eventRecurring = $this->eventRecurringService->createOneEventRecurringParent($data);
        if (!$eventRecurring) {
            return ApiResponse::error('Event recurring not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Event recurring created', ['eventRecurring' => $eventRecurring], Response::HTTP_CREATED);
    }
    //! ----------------------------------------------------------------------------------------

    private function createEventNewSupplier(Supplier $supplier): ApiResponse
    {
        [$businessName, $habits, $goodToKnow, $logistic, $categories,$date] = $this->getDataEvents($supplier);

        $data = [
            "section"     => "supplier",
            "description" => "Un nouveau fournisseur {$businessName->getName()} a été ajouté .
                            {$businessName->getName()} est un fournisseur de : {$categories}.
                            Nos habitudes : {$habits}, Logistique : {$logistic}, Bon à savoir : {$goodToKnow}.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Nouveau Supplier {$businessName->getName()}.",
            "dueDate" => $date
        ];
        $event = $this->eventService->createOneEvent($data);
        if (!$event) {
            return ApiResponse::error('Event not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Event created', ['event' => $event], Response::HTTP_CREATED);
    }

    //! ----------------------------------------------------------------------------------------

    private function isSupplierAlreadyExist(object $data): ApiResponse
    {
        $suppliers = $this->supplierRepository->findAll();
        $supplierNameAlreadyExist = array_filter($suppliers, fn($supplier) => $supplier->getName() == $data->getData()[ " supplierName" ]);
        if ($supplierNameAlreadyExist) {
            return ApiResponse::error("This supplier name already exist", null, Response::HTTP_BAD_REQUEST);
        } else {
            return ApiResponse::success("Supplier name is unique", null, Response::HTTP_OK);
        }
    }

    //! ----------------------------------------------------------------------------------------

    private function getDataEvents(Supplier $supplier): array
    {
        $businessName = $supplier->getBusiness();
        $habits = $supplier->getHabits();
        $goodToKnow = $supplier->getGoodToKnow();
        $logistic = $supplier->getLogistic();
        $date = $this->now->format('Y-m-d');
        $categories = $supplier->getCategories();
        $categories = $categories->map(fn($category) => $category->getName())->toArray();
        $categories = implode(", ", $categories);

        return [$businessName, $habits, $goodToKnow, $logistic, $categories, $date];
    }

    //! ----------------------------------------------------------------------------------------

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
}



