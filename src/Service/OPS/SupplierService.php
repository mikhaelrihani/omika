<?php
namespace App\Service\OPS;

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
        protected ContactService $contactService,
        protected SupplierEventService $supplierEventService

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

            $responseEvent = $this->supplierEventService->handleEventCreationsforNewSupplier($supplier, $responseData->getData());
            if (!$responseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseEvent->getMessage(), null, $responseEvent->getStatusCode());
            }

            $this->em->commit();
            $this->em->flush();

            return ApiResponse::success('Supplier created succesfully', ['supplier' => $supplier], Response::HTTP_CREATED);


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

            $ResponseEvent = $this->supplierEventService->handleEventCreationsforUpdatedSupplier($supplier, $data->getData());
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


            $ResponseEvent = $this->supplierEventService->createEventDeletedSupplier($supplier);
            if (!$ResponseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($ResponseEvent->getMessage(), null, $ResponseEvent->getStatusCode());
            }


            $responseStaff = $this->deleteStaffAndBusiness($supplier);
            if (!$responseStaff->isSuccess()) {
                $this->em->rollback();
                return $responseStaff;
            }



            $this->em->remove($supplier);
            $this->em->commit();
            $this->em->flush();

            return ApiResponse::success("Supplier deleted succesfully", null, Response::HTTP_OK);

        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
        $supplier->setBusiness(null);

        foreach ($staffs as $staff) {
            $response = $this->contactService->deleteContact($staff->getId());
            if (!$response->isSuccess()) {
                return $response;
            }
        }
        return ApiResponse::success('Staffs deleted succesfully', null, Response::HTTP_OK);
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

    /**
     * Handle the update of supplier relations.
     *
     * This method clears existing order days, delivery days, and categories associated with the supplier,
     * and reassigns them based on the provided response data. If an exception occurs during the process,
     * an error response is returned.
     *
     * @param Supplier   $supplier    The supplier entity whose relations are being updated.
     * @param ApiResponse $responseData The response data containing the new relations to assign.
     *
     * @return ApiResponse Returns an ApiResponse indicating success or failure.
     */
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
        return ApiResponse::success("Relations updated succesfully", null, Response::HTTP_OK);
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
        return ApiResponse::success('Category created succesfully', ['category' => $category], Response::HTTP_CREATED);
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Retrieves all categories.
     *
     * This method fetches all categories from the database.
     * If no categories are found, it returns an error response.
     *
     * @return ApiResponse Returns a success response with the list of categories
     *                     or an error response if no categories are found.
     */
    public function getSuppliersByCategory(int $id): ApiResponse
    {
        $catégory = $this->em->getRepository(Category::class)->find($id)->getName();
        $suppliers = $this->supplierRepository->findSuppliersByCategorie($id);
        if (!$suppliers) {
            return ApiResponse::error('Suppliers not found', [], Response::HTTP_NOT_FOUND);
        }

        $data = array_map(fn($supplier) => [
            'id'   => $supplier[ "id" ],
            'name' => $supplier[ "name" ],
        ], $suppliers);

        $count = count($data);
        $data[ 'category' ] = $catégory;
        $data[ 'count' ] = $count;

        return ApiResponse::success("{$count} Suppliers found for the catégory {$catégory}", ['suppliers' => $data], Response::HTTP_OK);
    }


}



