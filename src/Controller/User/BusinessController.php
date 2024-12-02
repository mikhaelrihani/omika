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

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves all businesses and returns them in a JSON response.
     *
     * This controller method fetches a list of businesses using the `businessService`.
     * If the retrieval is successful, it returns the list of businesses in the response.
     * Otherwise, it returns an appropriate error message.
     *
     * @Route("/getBusinesses", name="getBusinesses", methods={"GET"})
     *
     * @return JsonResponse A JSON response containing the list of businesses 
     *                      if successful, or an error message if the operation fails.
     */
    #[Route('/getBusinesses', name: 'getBusinesses')]
    public function getBusinesses(): JsonResponse
    {
        $response = $this->businessService->getBusinesses();
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "business" => $response->getData()[ "business" ]], $response->getStatusCode(), [], ['groups' => 'business']);
    }


    //! --------------------------------------------------------------------------------------------


    /**
     * Creates a new business and returns the result in a JSON response.
     *
     * This controller method processes the request to create a new business.
     * It delegates the creation logic to the `businessService`.
     * If the operation is successful, the new business is returned in the response.
     * Otherwise, an error message is provided.
     *
     * @Route("/create", name="create", methods={"POST"})
     *
     * @param Request $request The HTTP request containing the data for the new business.
     *
     * @return JsonResponse A JSON response containing the created business 
     *                      if successful, or an error message if the operation fails.
     */
    #[Route('/create', name: 'create', methods: 'post')]
    public function create(Request $request): JsonResponse
    {
        $response = $this->businessService->createBusiness($request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "business" => $response->getData()[ "business" ]], $response->getStatusCode(), [], ['groups' => 'business']);
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Updates an existing business with the provided data.
     *
     * This controller method processes the request to update an existing business.
     * It delegates the update logic to the `businessService`.
     * If the operation is successful, the updated business details are returned in the response.
     * Otherwise, an error message is provided.
     *
     * @Route("/update/{id}", name="update", methods={"PUT"})
     *
     * @param int $id The ID of the business to update.
     * @param Request $request The HTTP request containing the updated data for the business.
     *
     * @return JsonResponse A JSON response containing the updated business 
     *                      if successful, or an error message if the update fails.
     */
    #[Route('/update/{id}', name: 'update', methods: 'put')]
    public function update(int $id, Request $request): JsonResponse
    {
        $response = $this->businessService->updateBusiness($id, $request);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "business" => $response->getData()[ "business" ]], $response->getStatusCode(), [], ['groups' => 'business']);
    }

    //! --------------------------------------------------------------------------------------------

}
