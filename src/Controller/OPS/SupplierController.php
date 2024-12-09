<?php

namespace App\Controller\OPS;

use App\Service\OPS\SupplierService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/supplier', name: "app_supplier_")]
class SupplierController extends AbstractController
{

    public function __construct(
        private SupplierService $supplierService
    ) {
    }

    //! --------------------------------------------------------------------------------------------
    #[Route('/getSupplier/{id}', name: 'getSupplier', methods: 'get')]
    public function getSupplier(int $id): JsonResponse
    {
        $response = $this->supplierService->getSupplier($id);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "supplier" => $response->getData()[ "supplier" ]], $response->getStatusCode(), [], ['groups' => 'supplier']);
    }

    //! --------------------------------------------------------------------------------------------


    #[Route('/getSuppliers', name: 'getSuppliers', methods: 'get')]
    public function getSuppliers(): JsonResponse
    {
        $response = $this->supplierService->getSuppliers();
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "suppliers" => $response->getData()[ "suppliers" ]], $response->getStatusCode(), [], ['groups' => 'supplier']);
    }


    //! --------------------------------------------------------------------------------------------


    #[Route('/create', name: 'create', methods: 'post')]
    public function create(Request $request): JsonResponse
    {
        $response = $this->supplierService->createSupplier($request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "supplier" => $response->getData()[ "supplier" ]], $response->getStatusCode(), [], ['groups' => 'supplier']);
    }


    //! --------------------------------------------------------------------------------------------

    #[Route('/update/{id}', name: 'update', methods: 'put')]
    public function update(Request $request, int $id): JsonResponse
    {
        $response = $this->supplierService->updateSupplier($id, $request, );
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "supplier" => $response->getData()[ "supplier" ]], $response->getStatusCode(), [], ['groups' => 'supplier']);
    }

    //! --------------------------------------------------------------------------------------------

    #[Route('/delete/{id}', name: 'delete', methods: 'delete')]
    public function delete(int $id): JsonResponse
    {
        $response = $this->supplierService->deleteSupplier($id);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json($response->getMessage(), $response->getStatusCode());
    }

    //! --------------------------------------------------------------------------------------------

    #[Route('/createCategory', name: 'createCategory', methods: 'Post')]
    public function createCategory(Request $request): JsonResponse
    {
        $response = $this->supplierService->createCategory($request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "category" => $response->getData()[ "category" ]], $response->getStatusCode(), [], ['groups' => 'supplier']);

    }

    //! --------------------------------------------------------------------------------------------

    #[Route('/updateCategory/{id}', name: 'updateCategory', methods: 'put')]

    //! --------------------------------------------------------------------------------------------

    #[Route('/getSuppliersByCategory/{categoryId}', name: 'getSuppliersByCategory', methods: 'get')]
    public function getSuppliersByCategory($categoryId): JsonResponse
    {
        $response = $this->supplierService->getSuppliersByCategory($categoryId);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "suppliers" => $response->getData()[ "suppliers" ]], $response->getStatusCode(), [], ['groups' => 'supplier']);
    }

}


