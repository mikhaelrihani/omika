<?php

namespace App\Service\User;

use App\Entity\User\Business;
use App\Entity\User\User;
use App\Entity\User\UserLogin;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

class UserService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JsonResponseBuilder $jsonResponseBuilder,
        private UserPasswordHasherInterface $userPasswordHasher,
        private ValidatorService $validateService,
        private SerializerInterface $serializer
    ) {
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Finds a user entity by its ID.
     *
     * Retrieves a user record from the database using the given ID. If no user
     * is found, returns an error response with a 400 HTTP status code. On success,
     * returns the user data along with a success message.
     *
     * @param int $id The ID of the user record to retrieve.
     *
     * @return apiResponse
     *     - On success: Returns the user data and a success message with a 200 HTTP status.
     *     - On failure: Returns an error message with a 400 HTTP status.
     */
    public function findUser(int $id): apiResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return ApiResponse::error("There is no user with this id", null, Response::HTTP_BAD_REQUEST);
        }
        return ApiResponse::success("user retrieved successfully", ["user" => $user], Response::HTTP_OK);
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Finds a user login entity by its ID.
     *
     * Retrieves a user login record from the database using the given ID. If no user login
     * is found, returns an error response with a 400 HTTP status code. On success, returns
     * the user login data along with a success message.
     *
     * @param int $id The ID of the user login record to retrieve.
     *
     * @return apiResponse
     *     - On success: Returns the user login data and a success message with a 200 HTTP status.
     *     - On failure: Returns an error message with a 400 HTTP status.
     */
    public function findUserLogin(int $id): apiResponse
    {
        $userLogin = $this->em->getRepository(UserLogin::class)->find($id);

        if ($userLogin === null) {
            return ApiResponse::error("There is no userLogin with this id", null, Response::HTTP_BAD_REQUEST);
        }
        return ApiResponse::success("userLogin retrieved successfully", ["userLogin" => $userLogin], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Creates a new UserLogin entity and persists it to the database.
     *
     * @param array $data The data required to create the UserLogin entity. Expected keys:
     *                    - 'email' (string): The email address of the user.
     *                    - 'password' (string): The raw password of the user.
     *                    - 'roles' (array): An array of roles assigned to the user.
     *
     * @return ApiResponse Returns a success response with the created UserLogin entity 
     *                     or an error response if the creation fails.
     */
    public function createUserLogin(array $data): ApiResponse
    {
        try {
            $userLogin = new UserLogin();
            $userLogin
                ->setEmail($data[ 'email' ])
                ->setPassword($this->userPasswordHasher->hashPassword($userLogin, $data[ 'password' ]))
                ->setRoles($data[ 'roles' ]);

            $this->em->persist($userLogin);

            $response = $this->validateService->validateEntity($userLogin);

            if (!$response->isSuccess()) {
                return $response;
            }

            return ApiResponse::success("User login persisted successfully", ["userLogin" => $userLogin], Response::HTTP_CREATED);
        } catch (Exception $exception) {
            return ApiResponse::error("error while persisting user login : " . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Creates a new User entity, associates it with a UserLogin, and persists both to the database.
     *
     * @param array $data The data required to create the User entity. Expected keys:
     *                    - 'firstname' (string): The user's first name.
     *                    - 'surname' (string): The user's surname.
     *                    - 'pseudo' (string): The user's pseudo.
     *                    - 'phone' (string): The user's phone number.
     *                    - 'whatsapp' (string): The user's WhatsApp number.
     *                    - 'job' (string): The user's job title.
     *                    - 'businessId' (int): The ID of the associated Business entity.
     *                    - 'email' (string): The email address for UserLogin.
     *                    - 'password' (string): The password for UserLogin.
     *                    - 'roles' (array): An array of roles for UserLogin.
     *
     * @return ApiResponse Returns a success response with the created User entity 
     *                     or an error response if the creation fails.
     */
    public function createUser(array $data): ApiResponse
    {
        $this->em->beginTransaction();
        try {

            $user = (new User())
                ->setFirstname($data[ 'firstname' ])
                ->setSurname($data[ 'surname' ])
                ->setPseudo($data[ 'pseudo' ])
                ->setPhone($data[ 'phone' ])
                ->setWhatsapp($data[ 'whatsapp' ])
                ->setJob($data[ 'job' ])
                ->setLateCount(0)
                ->setPrivateNote('welcome to your personnal notes')
                ->setUuid(Uuid::v4());

            $business = $this->em->getRepository(Business::class)->find($data[ 'businessId' ]);
            if (!$business) {
                return ApiResponse::error("There is no business with this id", null, Response::HTTP_BAD_REQUEST);
            }
            $user->setBusiness($business);

            $userLogin = $this->createUserLogin($data);

            if (!$userLogin->isSuccess()) {
                return $userLogin;
            }
            $user->setUserLogin($userLogin->getData()[ 'userLogin' ]);

            $this->em->persist($user);

            $response = $this->validateService->validateEntity($user);
            if (!$response->isSuccess()) {
                return $response;
            }

            $this->em->commit();
            $this->em->flush();
            return ApiResponse::success("User created successfully", ["user" => $user], Response::HTTP_CREATED);

        } catch (Exception $exception) {
            $this->em->rollback();
            return ApiResponse::error("error while creating user :" . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------


    /**
     * Verifies if the provided password matches the hashed password of the given UserLogin entity.
     *
     * @param UserLogin $userLogin The UserLogin entity whose password needs to be validated.
     * @param string $password The raw password provided by the user.
     *
     * @return bool Returns true if the password is valid, false otherwise.
     */
    public function isPasswordValid(UserLogin $userLogin, string $password): bool
    {
        return $this->userPasswordHasher->isPasswordValid($userLogin, $password);
    }


    //! --------------------------------------------------------------------------------------------
    /**
     * Updates the password of a user.
     * 
     * This method validates the provided current password, checks if the new password 
     * matches its confirmation, hashes the new password, and persists the changes in the database.
     * 
     * @param int $userId The ID of the user whose password is to be updated.
     * @param string $currentPassword The user's current password (not validated in this method).
     * @param string $newPassword The new password the user wants to set.
     * @param string $newPasswordConfirmation The confirmation of the new password.
     * 
     * @return ApiResponse An API response indicating the outcome of the password update.
     *                      - On success: HTTP 200 with success message.
     *                      - On failure:
     *                        - HTTP 400 if the passwords do not match or the user is not found.
     *                        - HTTP 500 if an internal error occurs.
     * 
     * @throws Exception If there is an error while hashing or persisting the password.
     */

    public function updateUser(int $userId, string $data): ApiResponse
    {
        try {
            $user = $this->findUser($userId);
            if (!$user->isSuccess()) {
                return $user;
            }
            $user = $user->getData()[ 'user' ];
            // Mettre à jour l'objet User avec object_to_populate
            $this->serializer->deserialize($data, USER::class, 'json', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $user,
            ]);
            $response = $this->validateService->validateEntity($user);
            if (!$response->isSuccess()) {
                return $response;
            }
            $this->em->flush();
            return ApiResponse::success("User updated successfully", ["user" => $user], Response::HTTP_OK);
        } catch (Exception $exception) {
            return ApiResponse::error("error while updating user :" . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Updates the password of a user's login account.
     *
     * This method retrieves the user's login details, verifies if the new password matches
     * the confirmation, hashes the new password, and saves the change to the database.
     *
     * @param int $userId The unique identifier of the user whose password is being updated.
     * @param string $currentPassword The current password of the user (not validated in this implementation).
     * @param string $newPassword The new password the user wants to set.
     * @param string $newPasswordConfirmation The confirmation of the new password to ensure they match.
     *
     * @return ApiResponse Returns an ApiResponse object with the following outcomes:
     *     - Success (HTTP 200): Password updated successfully.
     *     - Error (HTTP 400): If the user is not found or the passwords do not match.
     *     - Error (HTTP 500): If an unexpected error occurs during the update.
     *
     * @throws Exception If there is an error hashing the password or during the database flush operation.
     */

    public function updatePassword(int $userId, string $currentPassword, string $newPassword, string $newPasswordConfirmation): ApiResponse
    {
        try {
            $userLogin = $this->findUserLogin($userId);
            if (!$userLogin->isSuccess()) {
                return $userLogin;
            }
            $userLogin = $userLogin->getData()[ "userLogin" ];

            if ($newPassword !== $newPasswordConfirmation) {
                return ApiResponse::error("Passwords do not match", null, Response::HTTP_BAD_REQUEST);
            }

            $userLogin->setPassword($this->userPasswordHasher->hashPassword($userLogin, $newPassword));

            $this->em->flush();
            return ApiResponse::success("Password updated successfully", [], Response::HTTP_OK);
        } catch (Exception $exception) {
            return ApiResponse::error("error while updating password :" . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}