<?php
namespace App\Service\OPS;

use App\Entity\Product\Product;
use App\Entity\Product\ProductType;
use App\Entity\Recipe\Unit;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Supplier\Supplier;
use App\Repository\Product\ProductRepository;
use App\Service\Event\EventService;
use App\Service\Media\FileService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use DateTimeImmutable;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class ProductService
{
    private DateTimeImmutable $now;
    function __construct(
        private EntityManagerInterface $em,
        private ProductRepository $productRepository,
        private ValidatorService $validateService,
        protected SerializerInterface $serializer,
        protected EventService $eventService,
        protected ProductEventService $productEventService,
        protected FileService $fileService,

    ) {
        $this->now = new DateTimeImmutable('today');
    }

    //! ----------------------------------------------------------------------------------------


    public function getProduct(int $id): ApiResponse
    {
        $product = $this->productRepository->find($id);
       
        if (!$product) {
            return ApiResponse::error('Product not found', [], Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success('Product found succesfully', ['product' => $product], Response::HTTP_OK);
    }


    //! ----------------------------------------------------------------------------------------


    public function getProducts(): ApiResponse
    {
        $products = $this->productRepository->findAll();
        if (!$products) {
            return ApiResponse::error('Products not found', [], Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success('Products found succesfully', ['products' => $products], Response::HTTP_OK);
    }


    //! ----------------------------------------------------------------------------------------

    public function createProduct(Request $request): ApiResponse
    {
        $this->em->beginTransaction();
        try {
            $responseData = $this->validateService->validateJson($request);
            if (!$responseData->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseData->getMessage(), null, $responseData->getStatusCode());
            }
//! verifier si le produit existe deja(validator unique constraint?)
            $product = $this->handleProductCreation($responseData);
            $responseValidation = $this->validateService->validateEntity($product);
            if (!$responseValidation->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseValidation->getMessage(), $responseValidation->getData()["errors"], $responseValidation->getStatusCode());
            }

            $responseEvent = $this->productEventService->createEventNewProduct($product, $product->getSupplier());
            if (!$responseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseEvent->getMessage(), null, $responseEvent->getStatusCode());
            }

            $this->em->commit();
            $this->em->flush();
            return ApiResponse::success('Product created succesfully', ['product' => $product], Response::HTTP_CREATED);

        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! ----------------------------------------------------------------------------------------

    public function updateProduct(int $id, Request $request): ApiResponse
    {
        $this->em->beginTransaction();
        try {
            $product = $this->productRepository->find($id);
            if (!$product) {
                $this->em->rollback();
                return ApiResponse::error(
                    "There is no product with this id",
                    null,
                    Response::HTTP_BAD_REQUEST
                );
            }

            $data = $this->validateService->validateJson($request);
            if (!$data->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($data->getMessage(), null, Response::HTTP_BAD_REQUEST);
            }

            $product = $this->serializer->deserialize($request->getContent(), Product::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $product]);

            if (!$product) {
                $this->em->rollback();
                return ApiResponse::error("Error while deserializing product", null, Response::HTTP_BAD_REQUEST);
            }

            $ResponseEvent = $this->productEventService->createEventUpdatedProduct($product, $product->getSupplier());
            if (!$ResponseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($ResponseEvent->getMessage(), null, $ResponseEvent->getStatusCode());
            }

            $this->em->commit();
            $this->em->flush();

            return ApiResponse::success("Succesfully updated {$product->getKichenName()}", ["product" => $product], Response::HTTP_OK);
        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_BAD_REQUEST);
        }

    }

    //! ----------------------------------------------------------------------------------------



    public function deleteProduct(int $id): ApiResponse
    {
        $this->em->beginTransaction();
        try {
            $product = $this->productRepository->find($id);
            if (!$product) {
                $this->em->rollback();
                return ApiResponse::error("There is no product with this id", null, Response::HTTP_BAD_REQUEST);
            }


            $responseRelation = $product->removeAllRelations();
            if (!$responseRelation) {
                $this->em->rollback();
                return ApiResponse::error("Error while removing relations", null, Response::HTTP_INTERNAL_SERVER_ERROR);
            }


            $ResponseEvent = $this->productEventService->createEventDeletedProduct($product, $product->getSupplier());
            if (!$ResponseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($ResponseEvent->getMessage(), null, $ResponseEvent->getStatusCode());
            }

            $this->em->remove($product);
            $this->em->commit();
            $this->em->flush();

            return ApiResponse::success("Product deleted succesfully", null, Response::HTTP_OK);

        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    //! ----------------------------------------------------------------------------------------

    private function handleProductCreation(ApiResponse $responseData): Product
    {
        $slug = $this->fileService->slugify($responseData->getData()[ 'commercialName' ]);
        $unit = $this->em->getRepository(Unit::class)->find($responseData->getData()[ 'unit' ]);
        $supplier = $this->em->getRepository(Supplier::class)->find($responseData->getData()[ 'supplier' ]);
        $type = $this->em->getRepository(ProductType::class)->find($responseData->getData()[ 'type' ]);

        $product = (new Product())
            ->setCommercialName($responseData->getData()[ 'commercialName' ])
            ->setKitchenName($responseData->getData()[ 'kitchenName' ])
            ->setSlug($slug)
            ->setPrice($responseData->getData()[ 'price' ])
            ->setConditionning($responseData->getData()[ 'conditionning' ])
            ->setUnit($unit)
            ->setSupplier($supplier)
            ->setType($type);

        $supplier->addProduct($product);

        $this->em->persist($product);
        return $product;

    }

    //! ----------------------------------------------------------------------------------------

    public function getProductBySupplier(int $id): ApiResponse
    {
        $supplier = $this->em->getRepository(Supplier::class)->find($id);
        if (!$supplier) {
            return ApiResponse::error('Supplier not found', [], Response::HTTP_NOT_FOUND);
        }

        $products = $supplier->getProducts();
        if (!$products) {
            return ApiResponse::error('Products not found', [], Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::success('Products found', ['products' => $products], Response::HTTP_OK);
    }

    //! ----------------------------------------------------------------------------------------

    public function toggleFavorite(int $id): ApiResponse
    {
        try {
            $currentProduct = $this->productRepository->find($id);
            if (!$currentProduct) {
                return ApiResponse::error('Product not found', [], Response::HTTP_NOT_FOUND);
            }
            $currentProduct->setFavorite(!$currentProduct->getFavorite());

            $KitchenName = $currentProduct->getKitchenName();
            $suppliers = $this->em->getRepository(Supplier::class)->findAll();
            foreach ($suppliers as $supplier) {
                $product = $supplier->getProducts()->filter(function ($product) use ($KitchenName) {
                    return $product->getKitchenName() === $KitchenName;
                })->first();
                if ($product && $currentProduct->getFavorite() === true) {
                    $product->setFavorite(false);
                }
            }
            $this->em->flush();
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Product favorite status updated succesfully', null, Response::HTTP_OK);
    }
}



