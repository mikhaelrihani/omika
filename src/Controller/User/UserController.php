<?php

namespace App\Controller\User;

use App\Controller\BaseController;
use App\Repository\User\UserLoginRepository;
use App\Repository\User\UserRepository;
use App\Service\Event\EventService;
use App\Service\Media\PictureService;
use App\Service\User\UserService;
use App\Service\ValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('api/user', name: "app_user_")]
class UserController extends BaseController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserLoginRepository $userLoginRepository,
        private EntityManagerInterface $em,
        private UserService $userService,
        private ValidatorService $validateService,
        private EventService $eventService,
        private PictureService $pictureService
    ) {
    }


    #[Route('/getAllUsers', name: 'getAllUsers', methods: 'get')]
    public function index(): JsonResponse
    {
        $users = $this->userRepository->findAll();
        if (!$users) {
            return $this->json("No users found", Response::HTTP_NOT_FOUND);
        }
        return $this->json(["message" => "Users retrieved successfully", "contacts" => $users], Response::HTTP_OK, [], ['groups' => 'user']);
    }
    //! --------------------------------------------------------------------------------------------


    #[Route('/getOneUser/{id}', name: 'getOneUser', methods: 'get')]
    public function getOneUser(int $id): JsonResponse
    {
        $response = $this->userService->findUser($id);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        return $this->json(["message" => $response->getMessage(), "user" => $response->getData()[ "user" ]], $response->getStatusCode(), [], ['groups' => 'user']);
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Handles user registration by validating the request, creating a new user, and persisting the user data.
     *
     * @param Request $request The HTTP request containing user registration data in JSON format.
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
            $response = $this->userService->createUser($response->getData());

            if (!$response->isSuccess()) {
                return $this->json([$response->getMessage(), $response->getData()], $response->getStatusCode());
            }
            return $this->json("Succesfully registered {$response->getData()[ 'user' ]->getfullname()}", Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->json("Failed to register user : " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Deletes a user and their associated user login if the provided password is valid.
     * Deletes the user from all events they are registered for.
     *
     * @param int $id The ID of the user to be deleted.
     * @param Request $request The HTTP request containing the user's password for verification.
     *
     * @return JsonResponse Returns a JSON response indicating the success or failure of the deletion process.
     *
     * @throws Exception If an error occurs during the deletion process, a failure response is returned and the transaction is rolled back.
     */
    #[Route('/delete/{id}', name: 'delete', methods: 'delete')]
    public function delete(int $id, Request $request): JsonResponse
    {
        $this->em->beginTransaction();
        try {

            $userLoginResponse = $this->userService->findUserLogin($id);

            if (!$userLoginResponse->isSuccess()) {
                return $this->json($userLoginResponse->getMessage(), $userLoginResponse->getStatusCode());
            }
            $userLogin = $userLoginResponse->getData()[ 'userLogin' ];

            // Validate the provided password
            $password = $this->validateService->validateJson($request)->getData()[ 'password' ];

            if (!$this->userService->isPasswordValid($userLogin, $password)) {
                return $this->json("Invalid password", Response::HTTP_BAD_REQUEST);
            }
            $user = $this->userService->findUser($id);
            if (!$user->isSuccess()) {
                return $this->json($user->getMessage(), $user->getStatusCode());
            }

            // Remove the user from all events
            $user = $user->getData()[ "user" ];
            $this->eventService->removeUserFromAllEvents($user);

            try {
                $this->em->remove($user);
            } catch (Exception $e) {
                return $this->json($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->em->commit();
            $this->em->flush();

            return $this->json("User deleted successfully", Response::HTTP_OK);

        } catch (Exception $e) {
            $this->em->rollback();
            return $this->json("Failed to delete user : {$e->getMessage()} ", Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Updates a user's details based on the provided JSON data.
     *
     * @Route("/update/{id}", name="update", methods={"put"})
     *
     * @param Request $request The HTTP request containing the JSON data for the user update.
     * @param int $id The ID of the user to be updated.
     *
     * @return JsonResponse
     *
     **/
    #[Route('/update/{id}', name: 'update', methods: 'put')]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $response = $this->validateService->validateJson($request);
            if (!$response->isSuccess()) {
                return $this->json($response->getMessage(), $response->getStatusCode());
            }
            $response = $this->userService->updateUser($id, $request->getContent());
            if (!$response->isSuccess()) {
                return $this->json([$response->getMessage(), $response->getData()], $response->getStatusCode());
            }
            return $this->json("{$response->getMessage()} : {$response->getData()[ 'user' ]->getfullname()}", Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->json("Failed to update user : " . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Updates the avatar of a user.
     *
     * This method processes a request to update the avatar for a specific user by delegating 
     * the task to the UserService. It handles errors and returns appropriate JSON responses.
     *
     * @Route("/updateAvatar/{id}", name="updateAvatar", methods="POST")
     *
     * @param Request $request The HTTP request containing the uploaded avatar file.
     * @param int $id The ID of the user whose avatar is being updated.
     *
     * @return JsonResponse A JSON response indicating the status of the operation.
     *
     * @throws Exception If an error occurs during the avatar update process.
     *
     */

    #[Route('/updateAvatar/{id}', name: 'updateAvatar', methods: 'post')]
    public function updateAvatar(Request $request, int $id): JsonResponse
    {
        try {
            $response = $this->pictureService->updateAvatar($request, $id, "user", $this);
            if (!$response->isSuccess()) {
                return $this->json([$response->getMessage(), $response->getData()], $response->getStatusCode());
            }
            return $this->json($response->getMessage(), Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->json($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    //! --------------------------------------------------------------------------------------------

}
