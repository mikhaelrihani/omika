<?php

namespace App\Service\User;

use App\Entity\User\Business;
use App\Entity\User\User;
use App\Repository\User\BusinessRepository;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class BusinessService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BusinessRepository $businessRepository,
        private SerializerInterface $serializer,
        private ValidatorService $validatorService,
        private CurrentUser $currentUser
    ) {
    }

    //! --------------------------------------------------------------------------------------------


    public function isLastUser(User $user): bool
    {
        $business = $user->getBusiness();
        $users = $business->getUsers();
        return (count($users) == 1) ? true : false;
    }

    //! --------------------------------------------------------------------------------------------


    public function getBusinesses(): ApiResponse
    {
        $business = $this->businessRepository->findAll();
        if (!$business) {
            return ApiResponse::error("No business found", null, Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success("Businesses retrieved successfully", ["business" => $business], Response::HTTP_OK);
    }
    //! --------------------------------------------------------------------------------------------


    public function createBusiness(Request $request): ApiResponse
    {
        try {
            $data = $this->validatorService->validateJson($request);
            if (!$data->isSuccess()) {
                return ApiResponse::error($data->getMessage(), null, Response::HTTP_BAD_REQUEST);
            }
            $business = (new Business())
                ->setName($data->getData()[ 'name' ]);
            $business->addUser($data->getData()[ 'contact' ]);

            $this->em->persist($business);
            $this->em->flush();

            return ApiResponse::success("Business created successfully", ["businessId" => $business->getId()], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_BAD_REQUEST);
        }
    }

    //! --------------------------------------------------------------------------------------------
    public function updateBusiness(int $id, Request $request): ApiResponse
    {
        try {
            $business = $this->businessRepository->find($id);
            if (!$business) {
                return ApiResponse::error("There is no business with this id", null, Response::HTTP_BAD_REQUEST);
            }
            $business = $this->serializer->deserialize($request->getContent(), Business::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $business]);
            $this->em->flush();

            return ApiResponse::success("Succesfully updated {$business->getName()}", ["businessId" => $business->getId()], Response::HTTP_OK);
        } catch (Exception $exception) {
            return ApiResponse::error($exception->getMessage(), null, Response::HTTP_BAD_REQUEST);
        }

    }
    //! --------------------------------------------------------------------------------------------


    public function delete(Request $request)
    {
        try {
            $data = $this->validatorService->validateJson($request);
            if (!$data->isSuccess()) {
                return ApiResponse::error($data->getMessage(), null, Response::HTTP_BAD_REQUEST);
            }
            $business = $this->businessRepository->find($data->getData()[ 'businessId' ]);
            if (!$business) {
                return ApiResponse::error("There is no business with this id", null, Response::HTTP_BAD_REQUEST);
            }
            $isLastUser = $this->isLastUser($business);
            if (!$isLastUser) {
                return ApiResponse::error("You can't delete this business because it has more than one user", null, Response::HTTP_BAD_REQUEST);
            }
            $this->em->remove($business);
            $this->em->flush();
            return ApiResponse::success("Business deleted successfully", null, Response::HTTP_OK);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}