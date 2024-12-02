<?php

namespace App\Controller\User;

use App\Controller\BaseController;
use App\Repository\User\UserLoginRepository;
use App\Repository\User\UserRepository;
use App\Service\Event\EventService;
use App\Service\Media\PictureService;
use App\Service\User\BusinessService;
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
        private PictureService $pictureService,
        private BusinessService $businessService,
    ) {
    }


    /**
     * Retrieves all users from the system.
     *
     * This controller method fetches all users from the database via the `userRepository`.
     * If users are found, they are returned in the response.
     * If no users are found, an error message is returned.
     *
     * @Route("/getAllUsers", name="getAllUsers", methods={"GET"})
     *
     * @return JsonResponse A JSON response containing the list of users 
     *                      if found, or an error message if no users are found.
     */
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

    /**
     * Retrieves a single user by their ID.
     *
     * This controller method fetches a user from the system using the provided ID.
     * It delegates the user retrieval to the `userService`.
     * If the user is found, it returns the user's details in the response.
     * Otherwise, an error message is provided.
     *
     * @Route("/getOneUser/{id}", name="getOneUser", methods={"GET"})
     *
     * @param int $id The ID of the user to retrieve.
     *
     * @return JsonResponse A JSON response containing the user data 
     *                      if found, or an error message if the user does not exist.
     */
    #[Route('/getOneUser/{id}', name: 'getOneUser', methods: 'get')]
    public function getOneUser(int $id): JsonResponse
    {
        $response = $this->userService->findUser($id);
        if ($response === null || !$response->isSuccess()) {
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
            if (!$response) {
                return $this->json("Invalid JSON data", Response::HTTP_BAD_REQUEST);
            }
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
     * Deletes a user from the system.
     *
     * This controller method deletes a user from the system by their ID.
     * It first retrieves the user using the `userService`, then removes the user from all events.
     * If the user is successfully removed from all events, the user is deleted from the database.
     * If any errors occur during the process, an error message is returned.
     *
     * @Route("/delete/{id}", name="delete", methods={"DELETE"})
     *
     * @param int $id The ID of the user to delete.
     *
     * @return JsonResponse A JSON response indicating the status of the operation.
     */
    #[Route('/delete/{id}', name: 'delete', methods: 'delete')]
    public function delete(int $id): JsonResponse
    {
        $this->em->beginTransaction();
        try {

            $user = $this->userService->findUser($id);
            if (!$user->isSuccess()) {
                return $this->json($user->getMessage(), $user->getStatusCode());
            }

            // Remove the user from all events
            $user = $user->getData()[ "user" ];
            $response = $this->eventService->removeUserFromAllEvents($user);
            if (!$response->isSuccess()) {
                return $this->json($response->getMessage(), $response->getStatusCode());
            }

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
