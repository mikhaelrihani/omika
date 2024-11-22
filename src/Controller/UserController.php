<?php

namespace App\Controller;

use App\Repository\User\UserLoginRepository;
use App\Repository\User\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('api/user', name: "app_user_")]
class UserController extends BaseController
{
    public function __construct(private UserRepository $userRepository, private UserLoginRepository $userLoginRepository)
    {

    }
    //! Get USER Login

    #[Route('/userLogin/{id}', name: 'getUserLogin', methods: 'post')]
    public function getUserLogin(int $id): JsonResponse
    {
        $userLogin = $this->userLoginRepository->find($id);
        if (!$userLogin) {
            return $this->json(["error" => "There is no userLogin with this id"], Response::HTTP_BAD_REQUEST);
        }
        return $this->json($userLogin, Response::HTTP_OK, [],["groups" => 'userLogin']);

    }

    //! Get USER

    #[Route('/getOneUser/{id}', name: 'getOneUser', methods: 'post')]
    public function getOneUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return $this->json(["error" => "There is no user with this id"], Response::HTTP_BAD_REQUEST);
        }
        return $this->json($user, Response::HTTP_OK, [],["groups" => 'user']);
    }


}
