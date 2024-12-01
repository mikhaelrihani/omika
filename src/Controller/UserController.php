<?php

namespace App\Controller;

use App\Repository\User\UserLoginRepository;
use App\Repository\User\UserRepository;
use App\Service\Event\EventService;
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
        private EventService $eventService
    ) {
    }

    //! --------------------------------------------------------------------------------------------


    #[Route('/userLogin/{id}', name: 'getUserLogin', methods: 'post')]
    public function getUserLogin(int $id): JsonResponse
    {
        $response = $this->userService->findUserLogin($id);
    
        return $this->json(["message" => $response->getMessage(), "user" => $response->getData()[ "userLogin" ]], $response->getStatusCode(), [], ['groups' => 'userLogin']);

    }
    //! --------------------------------------------------------------------------------------------


    #[Route('/getOneUser/{id}', name: 'getOneUser', methods: 'post')]
    public function getOneUser(int $id): JsonResponse
    {
        $response = $this->userService->findUser($id);
        return $this->json(["message" => $response->getMessage(), "user" => $response->getData()[ "user" ]], $response->getStatusCode(), [], ['groups' => 'user']);
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Toggles the "enabled" property of a user login entity by its ID.
     *
     * This endpoint accepts a POST request to toggle the enabled status
     * of a user login identified by the given ID. If the entity is not found,
     * it returns an error response.
     *
     * @Route("/toggleEnable/{id}", name="toggleEnable", methods="post")
     *
     * @param int $id The ID of the user login to toggle the enabled status.
     *
     * @return JsonResponse
     *     - Returns a success message with HTTP status 200 when the toggle is successful.
     *     - Returns an error message with HTTP status 400 if the user login entity is not found.
     */
    #[Route('/toggleEnable/{id}', name: 'toggleEnable', methods: 'post')]
    public function toggleEnable(int $id): JsonResponse
    {
        $userLogin = $this->userLoginRepository->find($id);
        if (!$userLogin) {
            return $userLogin;
        }
        $userLogin->setEnabled(!$userLogin->isEnabled());
        $this->em->flush();
        return $this->json("enable property toggle succesfully", Response::HTTP_OK);
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
                return $this->json($response->getMessage(), $response->getStatusCode());
            }
            return $this->json("Succesfully registered {$response->getData()[ 'user' ]->getfullname()}", Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->json("Failed to register user : ".$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
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
        # $this->em->beginTransaction();
        try {

            $userLoginResponse = $this->userService->findUserLogin($id);

            if (!$userLoginResponse->isSuccess()) {
                #$this->em->rollback();
                return $this->json($userLoginResponse->getMessage(), $userLoginResponse->getStatusCode());
            }
            $userLogin = $userLoginResponse->getData()[ 'userLogin' ];

            // Validate the provided password
            $password = $this->validateService->validateJson($request)->getData()[ 'password' ];

            if (!$this->userService->isPasswordValid($userLogin, $password)) {
                #$this->em->rollback();
                return $this->json("Invalid password", Response::HTTP_BAD_REQUEST);
            }
            $user = $this->userService->findUser($id);
            if (!$user->isSuccess()) {
                # $this->em->rollback();
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

        } catch (Exception $exception) {
            #$this->em->rollback();
            return $this->json("Failed to delete user", Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    //! --------------------------------------------------------------------------------------------
    // admin update user role, enable...
    // update my profile for avatar, new password..
    //removeUserFromAllEvents
}
