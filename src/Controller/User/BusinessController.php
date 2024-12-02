<?php

namespace App\Controller\User;

use App\Service\User\BusinessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('api/business', name: "app_businesst_")]
class BusinessController extends AbstractController
{

    public function __construct(
       
        private BusinessService $businessService,
       
    ) {
    }
    #[Route('/getBusinesses', name: 'getBusinesses')]
    public function getBusinesses(): JsonResponse
    {
        $response = $this->businessService->getBusinesses();
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "business" => $response->getData()[ "business" ]], $response->getStatusCode(), [], ['groups' => 'business']);
    }

    #[Route('/create', name: 'create', methods: 'post')]
    public function create(Request $request): JsonResponse
    {
        $response = $this->businessService->createBusiness($request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "business" => $response->getData()[ "business" ]], $response->getStatusCode(), [], ['groups' => 'business']);
    }

    #[Route('/update/{id}', name: 'update', methods: 'put')]
    public function update(int $id, Request $request): JsonResponse
    {
        $response = $this->businessService->updateBusiness($id, $request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "business" => $response->getData()[ "business" ]], $response->getStatusCode(), [], ['groups' => 'business']);
    }
}
