<?php

namespace App\Controller\User;

use App\Repository\User\UserLoginRepository;
use App\Repository\User\UserRepository;
use App\Service\Media\FileService;
use App\Service\User\UserService;
use App\Service\ValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('api/absence', name: "app_absence_")]
class AbsenceController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserLoginRepository $userLoginRepository,
        private EntityManagerInterface $em,
        public UserService $userService,
        private ValidatorService $validateService,
        private FileService $fileService
    ) {
    }

    //! --------------------------------------------------------------------------------------------
    #[Route('/updateLateCount/{entityName}/{id}', name: 'updateLateCount', methods: 'PUT')]
    public function updateLateCount(int $id, string $entityName, Request $request): JsonResponse
    {

    }
}
