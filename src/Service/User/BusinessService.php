<?php

namespace App\Service\User;

use App\Entity\User\Business;
use App\Entity\User\Contact;
use App\Repository\User\BusinessRepository;
use App\Repository\User\ContactRepository;
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
        private CurrentUser $currentUser,
        private ContactRepository $contactRepository
    ) {
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Checks if a contact is the last contact associated with a business.
     *
     * This method checks if a contact is the last contact associated with a business.
     * If the contact is the last one, it returns true; otherwise, it returns false.
     *
     * @param Contact $contact The contact entity to check.
     *
     * @return bool Returns true if the contact is the last one associated with a business, false otherwise.
     */
    public function isLastContact(Contact $contact): bool
    {
        $business = $contact->getBusiness();
        $contacts = $business->getContacts();
        return (count($contacts) == 1) ? true : false;
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves all businesses from the repository.
     *
     * This method fetches all business entities from the database using the
     * business repository. If no businesses are found, an error response is returned.
     * Otherwise, a success response is returned with the list of businesses.
     *
     * @return ApiResponse The API response containing the list of businesses or an error message.
     */
    public function getBusinesses(): ApiResponse
    {
        $businesses = $this->businessRepository->findAll();
        if (!$businesses) {
            return ApiResponse::error("No business found", null, Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success("Businesses retrieved successfully", ["businesses" => $businesses], Response::HTTP_OK);
    }
    //! --------------------------------------------------------------------------------------------


    /**
     * Creates a new business entity.
     *
     * This method creates a new business entity using the provided JSON data.
     * The method validates the input data, checks if the business name already exists,
     * and associates the business with a contact entity. If the business is created successfully,
     * a success response is returned with the new business entity. Otherwise, an error response is returned.
     *
     * @param object $data The validated request data containing the business name and contact ID.
     *
     * @return ApiResponse Returns a success response with the new business entity if created successfully,
     *                     or an error response if the business name already exists or validation fails.
     */
    public function createBusiness(object $data): ApiResponse
    {
        try {

            // Check if the business name already exists
            $exist = $this->isBusinessAlreadyExist($data);
            if (!$exist->isSuccess()) {
                return $exist;
            }

            // Create a new business entity
            $business = (new Business())
                ->setName($data->getData()[ "businessName" ]);

            // Find and associate the contact
            if(array_key_exists("contact", $data->getData())){
                $contact = $this->contactRepository->find($data->getData()[ 'contact' ]);
                if (!$contact) {
                    return ApiResponse::error("There is no contact with this ID", null, Response::HTTP_BAD_REQUEST);
                }
                $business->addContact($contact);
            }
           
            // Validate the business entity
            $response = $this->validatorService->validateEntity($business);
            if (!$response->isSuccess()) {
                return $response;
            }


            $this->em->persist($business);
            $this->em->flush();

            return ApiResponse::success("Business created successfully", ["business" => $business], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_BAD_REQUEST);
        }
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Updates an existing business entity.
     *
     * This method retrieves a business by its ID and updates its details using the provided JSON data.
     * The method ensures the business exists, validates the input data, and prevents duplicate business names.
     * After successful validation and deserialization, the business entity is updated and persisted.
     *
     * @param int $id The ID of the business to update.
     * @param Request $request The HTTP request containing the JSON payload with updated business details.
     *
     * @return ApiResponse Returns a success response with the updated business data, 
     *                     or an error response if validation fails or the business does not exist.
     */
    public function updateBusiness(int $id, Request $request): ApiResponse
    {
        try {
            $business = $this->businessRepository->find($id);
            if (!$business) {
                return ApiResponse::error("There is no business with this id", null, Response::HTTP_BAD_REQUEST);
            }

            $data = $this->validatorService->validateJson($request);
            if (!$data->isSuccess()) {
                return ApiResponse::error($data->getMessage(), null, Response::HTTP_BAD_REQUEST);
            }

            $exist = $this->isBusinessAlreadyExist($data);
            if (!$exist->isSuccess()) {
                return $exist;
            }

            $business = $this->serializer->deserialize($request->getContent(), Business::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $business]);
            $this->em->flush();

            return ApiResponse::success("Succesfully updated {$business->getName()}", ["business" => $business], Response::HTTP_OK);
        } catch (Exception $exception) {
            return ApiResponse::error($exception->getMessage(), null, Response::HTTP_BAD_REQUEST);
        }

    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Deletes a business entity.
     *
     * This method deletes a business entity from the database if the contact is the last user of the business.
     * If the contact is the last user, the business is removed from the database.
     * Otherwise, an error response is returned indicating that the business cannot be deleted.
     *
     * @param Contact $contact The contact entity associated with the business to delete.
     *
     * @return ApiResponse Returns a success response if the business is deleted successfully,
     *                     or an error response if the business cannot be deleted.
     */
    public function deleteIfLastContact(Contact $contact): ApiResponse
    {
        try {
            $business = $contact->getBusiness();
            $isLastContact = $this->isLastContact($contact);
            if ($isLastContact) {
                $this->em->remove($business);
                $this->em->flush();
            }
            return ApiResponse::success("Business deleted successfully if the user was last user of the business", null, Response::HTTP_OK);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Deletes a business entity.
     *
     * This method deletes a business entity from the database.
     *
     * @param Business $business The business entity to delete.
     *
     * @return ApiResponse Returns a success response if the business is deleted successfully,
     *                     or an error response if the business cannot be deleted.
     */
    public function delete(Business $business): ApiResponse
    {
        try {
            $this->em->remove($business);
            $this->em->flush();
            return ApiResponse::success("Business deleted successfully ", null, Response::HTTP_OK);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Checks if a business name already exists in the repository.
     *
     * This method retrieves all businesses from the repository and checks if any existing business
     * has the same name as the one provided in the request data. If a match is found, it returns an error response.
     * Otherwise, it confirms the uniqueness of the business name.
     *
     * @param object $data The validated request data containing the `name` field of the business to check.
     *
     * @return ApiResponse Returns an error response if a business with the same name exists,
     *                     or a success response if the name is unique.
     */
    private function isBusinessAlreadyExist(object $data): ApiResponse
    {
        $businesses = $this->businessRepository->findAll();
        $businessNameAlreadyExist = array_filter($businesses, fn($business) => $business->getName() == $data->getData()[ "businessName" ]);
        if ($businessNameAlreadyExist) {
            return ApiResponse::error("This business name already exist", null, Response::HTTP_BAD_REQUEST);
        } else {
            return ApiResponse::success("Business name is unique", null, Response::HTTP_OK);
        }
    }

    //! --------------------------------------------------------------------------------------------


    /**
     * Retrieves a business entity from the database.
     *
     * This method fetches a business entity from the database using its ID.
     * If the business is found, it returns a success response with the business entity.
     * Otherwise, it returns an error response indicating that the business was not found.
     *
     * @param int $id The ID of the business entity to retrieve.
     *
     * @return ApiResponse Returns a success response with the business entity if found,
     *                     or an error response if the business is not found.
     */
    public function getBusinessFromData(object $dataObject)
    {
        $data = $dataObject->getData();
        if (!$data[ 'businessId' ]) {
            if ($data[ "businessName" ]) {
                $businessResponse = $this->createBusiness($dataObject);
                if (!$businessResponse->isSuccess()) {
                    return $businessResponse;
                }
                $business = $businessResponse->getData()[ 'business' ];
            } else {
                return ApiResponse::error(" missing property 'businessName' ", null, Response::HTTP_BAD_REQUEST);
            }
        } elseif ($data[ 'businessId' ]) {
            $business = $this->em->getRepository(Business::class)->find($data[ 'businessId' ]);
            if (!$business) {
                return ApiResponse::error("There is no business with this id", null, Response::HTTP_BAD_REQUEST);
            }
        }
        return ApiResponse::success("Business retrieved successfully", ["business" => $business], Response::HTTP_OK);
    }
}