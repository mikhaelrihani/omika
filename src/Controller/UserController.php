<?php

namespace App\Controller;

use App\Repository\User\UserLoginRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/user', name:"app_user_" )]
class UserController extends BaseController
{

    //! Get USER Login

    #[Route('/userLogin/{id}', name: 'getUserLogin', methods: 'GET')]
    public function getUserLogin(int $id, UserLoginRepository $userLoginRepository): JsonResponse
    {
        $userLogin = $userLoginRepository->find($id);
        if (!$userLogin) {
            return $this->json(["error" => "There is no userLogin with this id"], Response::HTTP_BAD_REQUEST);
        }
        return $this->json($userLogin, Response::HTTP_OK, []);

    }


}
