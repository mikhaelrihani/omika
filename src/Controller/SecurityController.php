<?php

namespace App\Controller;

use App\Service\JwtTokenService;
use App\Service\SecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/security', name: "app_security_")]
class SecurityController extends AbstractController
{
    private SecurityService $securityService;
    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    #[Route('/logout', name: "logout", methods: ["POST"])]
    public function logout(Request $request, JwtTokenService $jwtTokenService): JsonResponse
    {
        $refreshTokenToRevoke = $jwtTokenService->getRefreshTokenFromRequest($request);
        $jwtTokenService->revokeUserTokenAccess($refreshTokenToRevoke);
        return new JsonResponse(['message' => 'Logged out successfully.RefreshToken revoked'], Response::HTTP_OK);
    }

    #[Route('/newPassword', name: "newPassword", methods: ["POST"])]
    public function setNewPassword(Request $request): JsonResponse
    {
        // rafraichir le user password
        $this->securityService->refreshPassword($request);
        return new JsonResponse(['message' => 'Password updated successfully'], Response::HTTP_OK);

    }

    #[Route('/sendPasswordLink', name: "resetPassword", methods: ["POST"])]
    public function sendPasswordLink(Request $request): JsonResponse
    {
        // Envoyer un email avec le lien
        $email = $this->getUser()->getUserIdentifier();
        $this->securityService->sendPasswordResetLink($email);
        return new JsonResponse(['message' => 'Password reset link sent successfully'], Response::HTTP_OK);
    }

}
