<?php

namespace App\Service\User;


use App\Entity\User\Business;
use App\Entity\User\Contact;
use App\Service\Media\PictureService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
        private PictureService $pictureService
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
     * This method takes an array of contact data, validates it, and persists a new 
     * contact entity in the database. If the associated business ID is invalid, 
     * or if the contact data is invalid, an error response is returned.
     *
     * @param array $data An associative array containing the following keys:
     *                    - 'firstname': string, the first name of the contact.
     *                    - 'surname': string, the surname of the contact.
     *                    - 'email': string, the email of the contact.
     *                    - 'phone': string, the phone number of the contact.
     *                    - 'whatsapp': string, the WhatsApp number of the contact.
     *                    - 'job': string, the job title of the contact.
     *                    - 'businessId': int, the ID of the business to associate with the contact.
     *
     * @return ApiResponse Returns a success response with the created contact 
     *                     if successful, or an error response if something fails.
     */
    public function createContact(array $data): ApiResponse
    {

        try {

            $contact = (new Contact())
                ->setFirstname($data[ 'firstname' ])
                ->setSurname($data[ 'surname' ])
                ->setEmail($data[ 'email' ])
                ->setPhone($data[ 'phone' ])
                ->setWhatsapp($data[ 'whatsapp' ])
                ->setJob($data[ 'job' ])
                ->setLateCount(0)
                ->setUuid(Uuid::v4());

            $business = $this->em->getRepository(Business::class)->find($data[ 'businessId' ]);
            if (!$business) {
                return ApiResponse::error("There is no business with this id", null, Response::HTTP_BAD_REQUEST);
            }
            $contact->setBusiness($business);

            $this->em->persist($contact);

            $response = $this->validateService->validateEntity($contact);
            if (!$response->isSuccess()) {
                return $response;
            }


            $this->em->flush();
            return ApiResponse::success("Contact created successfully", ["contact" => $contact], Response::HTTP_CREATED);

        } catch (Exception $exception) {

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


}