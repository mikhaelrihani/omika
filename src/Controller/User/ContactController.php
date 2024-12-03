<?php

namespace App\Controller\User;

use App\Controller\BaseController;
use App\Repository\User\ContactRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\User\UserLoginRepository;
use App\Repository\User\UserRepository;
use App\Service\Event\EventService;
use App\Service\Media\PictureService;
use App\Service\User\BusinessService;
use App\Service\User\ContactService;
use App\Service\User\UserService;
use App\Service\ValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;

#[Route('api/contact', name: "app_contact_")]
class ContactController extends BaseController
{

    public function __construct(
        private UserRepository $userRepository,
        private UserLoginRepository $userLoginRepository,
        private ContactRepository $contactRepository,
        private EntityManagerInterface $em,
        public UserService $userService,
        private ValidatorService $validateService,
        private EventService $eventService,
        private PictureService $pictureService,
        public ContactService $contactService,
        private BusinessService $businessService
    ) {
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves all contacts from the repository.
     *
     * @Route("/getAllContact", name="getAllContact", methods={"get"})
     *
     * @return JsonResponse
     *
     * - Returns a JSON response containing:
     *   - A success message and the list of contacts if contacts are found.
     *   - An error message if no contacts are found.
     * - Includes a serialization group 'contact' for response data formatting.
     */
    #[Route('/getAllContact', name: 'getAllContact', methods: 'get')]
    public function index(): JsonResponse
    {
        $contacts = $this->contactRepository->findAll();
        if (!$contacts) {
            return $this->json("No contact found", Response::HTTP_NOT_FOUND);
        }
        return $this->json(["message" => "Contacts retrieved successfully", "contacts" => $contacts], Response::HTTP_OK, [], ['groups' => 'contact']);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves a single contact by its ID.
     *
     * @Route("/getOneContact/{id}", name="getOneContact", methods={"get"})
     *
     * @param int $id The ID of the contact to retrieve.
     *
     * @return JsonResponse
     *
     * - Returns a JSON response containing:
     *   - A success message and the contact data if the contact is found.
     *   - An error message if the contact is not found or an error occurs.
     * - Includes a serialization group 'contact' for response data formatting.
     */
    #[Route('/getOneContact/{id}', name: 'getOneContact', methods: 'get')]
    public function getOneContact(int $id): JsonResponse
    {
        $response = $this->contactService->findContact($id);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "contact" => $response->getData()[ "contact" ]], $response->getStatusCode(), [], ['groups' => 'contact']);
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Handles contact registration by validating the request, creating a new contact, and persisting the contact data.
     *
     * @param Request $request The HTTP request containing contact registration data in JSON format.
     *
     * @return JsonResponse Returns a JSON response indicating the success or failure of the registration process.
     *
     * @throws Exception If an error occurs during the registration process, it is caught and a failure response is returned.
     *
     * @Route("/register", name="register", methods={"POST"})
     */
    #[Route('/register', name: 'register', methods: 'post')]
    public function register(Request $request): JsonResponse
    {
        try {
            $response = $this->validateService->validateJson($request);
            if (!$response->isSuccess()) {
                return $this->json($response->getMessage(), $response->getStatusCode());
            }
            $response = $this->contactService->createContact($response);

            if (!$response->isSuccess()) {
                return $this->json([$response->getMessage(), $response->getData()], $response->getStatusCode());
            }
            return $this->json("Succesfully registered {$response->getData()[ 'contact' ]->getfullname()}", Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->json("Failed to register contact : " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Deletes a contact and their associated user login if the provided password is valid.
     * Deletes the contact from all events they are registered for.
     *
     * @param int $id The ID of the contact to be deleted.
     * @param Request $request The HTTP request containing the contact's password for verification.
     *
     * @return JsonResponse Returns a JSON response indicating the success or failure of the deletion process.
     */
    #[Route('/delete/{id}', name: 'delete', methods: 'delete')]
    public function delete(int $id): JsonResponse
    {
        $this->em->beginTransaction();
        try {
            $contact = $this->contactService->findContact($id);
            if (!$contact->isSuccess()) {
                $this->em->rollback();
                return $this->json($contact->getMessage(), $contact->getStatusCode());
            }

            // delete the business if the Contact is the last user in the business
            $response = $this->businessService->deleteIfLastContact($contact->getData()[ 'contact' ]);
            if (!$response->isSuccess()) {
                $this->em->rollback();
                return $this->json($response->getMessage(), $response->getStatusCode());
            }
            try {
                $this->em->remove($contact->getData()[ 'contact' ]);
            } catch (Exception $e) {
                $this->em->rollback();
                return $this->json($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $this->em->commit();
            $this->em->flush();
            return $this->json("Contact deleted successfully", Response::HTTP_OK);

        } catch (Exception $e) {
            $this->em->rollback();
            return $this->json("Failed to delete contact : {$e->getMessage()} ", Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Updates an existing contact based on the provided data.
     *
     * @Route("/update/{id}", name="update", methods={"put"})
     *
     * @param Request $request The HTTP request containing the JSON data for the update.
     * @param int $id The ID of the contact to update.
     *
     * @return JsonResponse
     */
    #[Route('/update/{id}', name: 'update', methods: 'put')]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $response = $this->validateService->validateJson($request);
            if (!$response->isSuccess()) {
                return $this->json($response->getMessage(), $response->getStatusCode());
            }
            $contact = $this->contactService->updateContact($id, $request->getContent());
            if (!$contact->isSuccess()) {
                return $this->json([$contact->getMessage(), $contact->getData()], $contact->getStatusCode());
            }
            return $this->json("{$response->getMessage()} : {$response->getData()[ 'contact' ]->getfullname()}", Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->json("Failed to update contact : " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------



}
