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

   
    public function findContact(int $id): apiResponse
    {
        $contact = $this->em->getRepository(Contact::class)->find($id);
        if (!$contact) {
            return ApiResponse::error("There is no contact with this id", null, Response::HTTP_BAD_REQUEST);
        }
        return ApiResponse::success("contact retrieved successfully", ["contact" => $contact], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

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



}