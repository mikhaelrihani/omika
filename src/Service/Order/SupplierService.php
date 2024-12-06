<?php
namespace app\Service\Order;

use Doctrine\ORM\EntityManagerInterface;
use app\Entity\Supplier\Supplier;
use App\Repository\Supplier\SupplierRepository;
use App\Service\Event\EventRecurringService;
use App\Service\Event\EventService;
use App\Service\User\BusinessService;
use App\Service\ValidatorService;
use app\Utils\ApiResponse;
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
        protected EventService $eventService

    ) {
        $this->now = new DateTimeImmutable('today');
    }

    //! ----------------------------------------------------------------------------------------

    public function getSupplier(int $id): ApiResponse
    {
        $supplier = $this->supplierRepository->find($id);
        if (!$supplier) {
            return ApiResponse::error('Supplier not found', [], Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success('Supplier found', ['supplier' => $supplier]);
    }

    //! ----------------------------------------------------------------------------------------

    public function getSuppliers(): ApiResponse
    {
        $suppliers = $this->supplierRepository->findAll();
        if (!$suppliers) {
            return ApiResponse::error('Suppliers not found', [], Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success('Suppliers found', ['suppliers' => $suppliers]);
    }

    //! ----------------------------------------------------------------------------------------

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
                ->setBusiness($businessResponse->getData()[ 'businessName' ]);

            $orderDays = $responseData->getData()[ 'orderDays' ];
            foreach ($orderDays as $orderDay) {
                $supplier->addOrderDay($orderDay);
            }

            $deliveryDays = $responseData->getData()[ 'deliveryDays' ];
            foreach ($deliveryDays as $deliveryDay) {
                $supplier->addDeliveryDay($deliveryDay);
            }

            $this->em->persist($supplier);


            $responseValidation = $this->validateService->validateEntity($supplier);
            if (!$responseValidation->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseValidation->getMessage(), null, $responseValidation->getStatusCode());
            }

            $responseEvent = $this->createEventRecuring($supplier);
            if (!$responseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseEvent->getMessage(), null, $responseEvent->getStatusCode());
            }
            $supplier->setRecurringEvent($responseEvent->getData()[ 'eventRecurring' ]);


            $ResponseEvent=$this->eventService->createOneEvent($data);
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
            $reponseEvent = $this->createEventRecuring($supplier);
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

    public function deleteSupplier(int $id): ApiResponse
    {


    }

    //! ----------------------------------------------------------------------------------------

    private function createEventRecuring(Supplier $supplier): ApiResponse
    {
        $businessName = $supplier->getBusiness();
        $habits = $supplier->getHabits();
        $goodToKnow = $supplier->getGoodToKnow();
        $logistic = $supplier->getLogistic();
        $date = $this->now->format('Y-m-d');

        $data = [
            "section"     => "supplier",
            "description" => "Mettre à jour la commande du fournisseur {$businessName} et passer la commande.
                            Nos habitudes : {$habits}, Logistique : {$logistic}, Bon à savoir : {$goodToKnow}",
            "type"        => "task",
            "side"        => "office",
            "title"       => "Commande {$businessName} du {$date}",
        ];
        $eventRecurring = $this->eventRecurringService->createOneEventRecurringParent($data);
        if (!$eventRecurring) {
            return ApiResponse::error('Event recurring not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Event recurring created', ['eventRecurring' => $eventRecurring], Response::HTTP_CREATED);
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
}



