<?php

namespace App\Controller\Supplier;

use App\Service\OPS\ProductService;
use App\Utils\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/product', name: "app_product_")]
class ProductController extends AbstractController
{

    public function __construct(
        private ProductService $productService
    ) {
    }

    //! --------------------------------------------------------------------------------------------
    #[Route('/getProduct/{id}', name: 'getProduct', methods: 'get')]
    public function getProduct(int $id): JsonResponse
    {
        $response = $this->productService->getProduct($id);
        return $this->getResponse($response);
    }

    //! --------------------------------------------------------------------------------------------


    #[Route('/getProducts', name: 'getProducts', methods: 'get')]
    public function getProducts(): JsonResponse
    {
        $response = $this->productService->getProducts();
        return $this->getResponse($response, 'products');
    }


    //! --------------------------------------------------------------------------------------------


    #[Route('/create', name: 'create', methods: 'post')]
    public function create(Request $request): JsonResponse
    {
        $response = $this->productService->createProduct($request);
        return $this->getResponse($response);
    }


    //! --------------------------------------------------------------------------------------------

    #[Route('/update/{id}', name: 'update', methods: 'put')]
    public function update(Request $request, int $id): JsonResponse
    {
        $response = $this->productService->updateProduct($id, $request, );
        return $this->getResponse($response);
    }

    //! --------------------------------------------------------------------------------------------

    #[Route('/delete/{id}', name: 'delete', methods: 'delete')]
    public function delete(int $id): JsonResponse
    {
        $response = $this->productService->deleteProduct($id);
        return $this->getResponse($response, null, false);
    }

    //! --------------------------------------------------------------------------------------------


    #[Route('/getProductBySupplier/{id}', name: 'getProductBySupplier', methods: 'get')]
    public function getProductBySupplier(int $id): JsonResponse
    {
        $response = $this->productService->getProductBySupplier($id);
        return $this->getResponse($response);
    }


    //! --------------------------------------------------------------------------------------------

    #[Route('/toggleFavorite/{id}', name: 'toggleFavorite', methods: 'put')]
    public function toggleFavorite(int $id): JsonResponse
    {
        $response = $this->productService->toggleFavorite($id);
        return $this->getResponse($response, null, false);
    }

    //! --------------------------------------------------------------------------------------------

    private function getResponse(ApiResponse $response, ?string $entity = "product", $jsonData = true): JsonResponse
    {
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        $jsonData = $jsonData ?
            ["message" => $response->getMessage(), $entity => $response->getData()[$entity]] :
            null;

        return $this->json(
            $jsonData,
            $response->getStatusCode(),
            [],
            ['groups' => "product"]
        );
    }


}


