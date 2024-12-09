<?php

namespace App\Controller\OPS;

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

    #[Route('/toggleFavorite/{id}', name: 'toggleFavorite', methods: 'post')]
    public function toggleFavorite(int $id): JsonResponse
    {
        $response = $this->productService->toggleFavorite($id);
        return $this->getResponse($response, null, false);
    }


    //! --------------------------------------------------------------------------------------------

    #[Route('/findProductSuppliers/{productId}', name: 'findProductSuppliers', methods: 'get')]
    public function findProductSuppliers(int $productId): JsonResponse
    {
        $response = $this->productService->getProductSuppliers($productId);
        return $this->getResponse($response, "suppliers", true, "supplier");

    }

    //! --------------------------------------------------------------------------------------------

    #[Route('/search', name: 'search', methods: 'get')]
    public function search(Request $request): JsonResponse
    {
        $response = $this->productService->searchProduct($request);
        return $this->getResponse($response, "products");
    }

    //! --------------------------------------------------------------------------------------------

    #[Route('/getProductRecipes/{productId}', name: 'getProductRecipes', methods: 'get')]
    public function getProductRecipes(int $productId): JsonResponse
    {
        $response = $this->productService->getProductRecipes($productId);
        return $this->getResponse($response, "recipes", true, "recipe");
    }

    //! --------------------------------------------------------------------------------------------

    private function getResponse(ApiResponse $response, ?string $entity = "product", $jsonData = true, $group = "product"): JsonResponse
    {
        if (!$response->isSuccess()) {
            return $this->json(["message" => $response->getMessage(), "data" => $response->getData()], $response->getStatusCode());
        }
        $jsonData = $jsonData ?
            [$entity => $response->getData()[$entity]] :
            null;
        $jsonData[ 'message' ] = $response->getMessage();
        
        return $this->json(
            $jsonData,
            $response->getStatusCode(),
            [],
            ['groups' => $group]
        );
    }



}