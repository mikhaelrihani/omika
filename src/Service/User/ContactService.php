<?php

namespace App\Service\User;

use App\Entity\User\Contact;
use App\Service\Media\PictureService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

class ContactService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JsonResponseBuilder $jsonResponseBuilder,
        private UserPasswordHasherInterface $userPasswordHasher,
        private ValidatorService $validateService,
        private SerializerInterface $serializer,
        private SluggerInterface $slugger,
        private ParameterBagInterface $params,
        private PictureService $pictureService,
        private BusinessService $businessService
    ) {
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves a contact by its ID.
     *
     * This method fetches a contact entity from the database using the provided ID. 
     * If no contact is found with the given ID, it returns an error response. 
     * Otherwise, it returns a success response with the retrieved contact data.
     *
     * @param int $id The ID of the contact to retrieve.
     *
     * @return ApiResponse Returns an error response if no contact is found,
     *                     or a success response with the contact data if found.
     */
    public function findContact(int $id): apiResponse
    {
        $contact = $this->em->getRepository(Contact::class)->find($id);
        if (!$contact) {
            return ApiResponse::error("There is no contact with this id", null, Response::HTTP_BAD_REQUEST);
        }
        return ApiResponse::success("contact retrieved successfully", ["contact" => $contact], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Creates a new contact and associates it with a business.
     *
     * This method handles the creation of a new contact entity, validates it, associates it with an existing
     * business retrieved from the provided data, and persists it in the database. If an error occurs during
     * the process, the associated business is deleted to maintain data integrity.
     *
     * @param object $dataObject An object containing the data required to create a contact. 
     *                           Expected structure:
     *                           [
     *                               'firstname' => string,
     *                               'surname' => string,
     *                               'email' => string,
     *                               'phone' => string,
     *                               'whatsapp' => string,
     *                               'job' => string,
     *                               'businessId' => int ou null
     *                               'businessName' => string
     *                           ].
     *
     * @return ApiResponse Returns a success response with the created contact if successful, 
     *                     or an error response with a message if an exception occurs.
     */
    public function createContact(object $dataObject): ApiResponse
    {

        try {
            $data = $dataObject->getData();
            $contact = (new Contact())
                ->setFirstname($data[ 'firstname' ])
                ->setSurname($data[ 'surname' ])
                ->setEmail($data[ 'email' ])
                ->setPhone($data[ 'phone' ])
                ->setWhatsapp($data[ 'whatsapp' ])
                ->setJob($data[ 'job' ])
                ->setLateCount(0)
                ->setUuid(Uuid::v4());

            $businessResponse = $this->businessService->getBusinessFromData($dataObject);
            if (!$businessResponse->isSuccess()) {
                return $businessResponse;
            }
            $business = $businessResponse->getData()[ 'business' ];
            $contact->setBusiness($business);
            $business->addContact($contact);

            $this->em->persist($contact);

            $response = $this->validateService->validateEntity($contact);
            if (!$response->isSuccess()) {
                return $response;
            }
            $this->em->flush();
            return ApiResponse::success("Contact created successfully", ["contact" => $contact], Response::HTTP_CREATED);

        } catch (Exception $exception) {
            $this->businessService->delete($business);
            return ApiResponse::error("error while creating contact :" . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------


    /**
     * Updates an existing contact with new data.
     *
     * This method fetches an existing contact by its ID, updates its properties 
     * using the provided JSON data, validates the updated entity, and saves the 
     * changes to the database.
     *
     * @param int $contactId The ID of the contact to be updated.
     * @param string $data A JSON string containing the updated contact data.
     *
     * @return ApiResponse Returns a success response with the updated contact 
     *                     if successful, or an error response if something fails.
     */
    public function updateContact(int $contactId, string $data): ApiResponse
    {
        try {
            $contact = $this->findContact($contactId);
            if (!$contact->isSuccess()) {
                return $contact;
            }
            $contact = $contact->getData()[ 'user' ];

            $this->serializer->deserialize($data, Contact::class, 'json', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $contact,
            ]);
            $response = $this->validateService->validateEntity($contact);
            if (!$response->isSuccess()) {
                return $response;
            }
            $this->em->flush();
            return ApiResponse::success("Contact updated successfully", ["contact" => $contact], Response::HTTP_OK);
        } catch (Exception $exception) {
            return ApiResponse::error("error while updating contact :" . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Autocomplete contact names based on partial input.
     *
     * @param Request $request HTTP request containing the 'query' parameter.
     *
     * @return ApiResponse JSON response with a list of possible contacts.
     */
    public function autocompleteContact(Request $request): ApiResponse
    {
        $query = $request->query->get('query');
        if (empty($query)) {
            return ApiResponse::error("Query parameter is required", null, Response::HTTP_BAD_REQUEST);
        }

        // Search for contacts with matching names
        $contacts = $this->em->getRepository(Contact::class)->createQueryBuilder('c')
            ->where('LOWER(c.firstname) LIKE :query OR LOWER(c.surname) LIKE :query')
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->setMaxResults(10) // Limit the number of results for performance
            ->getQuery()
            ->getResult();

        // If no contacts are found
        if (empty($contacts)) {
            return ApiResponse::error("No contacts found", null, Response::HTTP_NOT_FOUND);
        }

        // Format response data
        $serializedContacts = array_map(function ($contact) {
            return [
                'id'        => $contact->getId(),
                'firstname' => $contact->getFirstname(),
                'surname'   => $contact->getSurname(),
                'email'     => $contact->getEmail(),
            ];
        }, $contacts);

        return ApiResponse::success("Contacts found", ["contacts" => $serializedContacts], Response::HTTP_OK);
    }

}