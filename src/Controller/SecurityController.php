<?php

namespace App\Controller;

use App\Service\JwtTokenService;
use App\Service\SecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SecurityController
 * 
 * Gère les opérations liées à la sécurité, telles que la déconnexion et la réinitialisation de mot de passe.
 */
#[Route('/api/security', name: "app_security_")]
class SecurityController extends AbstractController
{
    private SecurityService $securityService;

    /**
     * SecurityController constructor.
     *
     * @param SecurityService $securityService Service gérant la logique de sécurité.
     */
    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Déconnecte l'utilisateur en révoquant son token JWT.
     * 
     * @Route("/logout", name="logout", methods={"POST"})
     *
     * @param Request $request La requête HTTP.
     * @param JwtTokenService $jwtTokenService Service pour manipuler les tokens JWT.
     * 
     * @return JsonResponse Réponse indiquant le succès de l'opération.
     */
    public function logout(Request $request, JwtTokenService $jwtTokenService): JsonResponse
    {
        $refreshTokenToRevoke = $jwtTokenService->getRefreshTokenFromRequest($request);
        $jwtTokenService->revokeUserTokenAccess($refreshTokenToRevoke);

        return new JsonResponse(['message' => 'Logged out successfully. Refresh token revoked'], Response::HTTP_OK);
    }

    /**
     * Définit un nouveau mot de passe pour l'utilisateur.
     * 
     * @Route("/newPassword", name="newPassword", methods={"POST"})
     *
     * @param Request $request La requête HTTP contenant le nouveau mot de passe et sa confirmation.
     * 
     * @return JsonResponse Réponse indiquant le succès ou l'échec de l'opération.
     */
    public function setNewPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['newPassword'], $data['confirmPassword'])) {
                return new JsonResponse(['message' => 'Missing required fields (newPassword or confirmPassword)'], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['newPassword']) || empty($data['confirmPassword'])) {
                return new JsonResponse(['message' => 'Password cannot be empty'], Response::HTTP_BAD_REQUEST);
            }

            if ($data['newPassword'] !== $data['confirmPassword']) {
                return new JsonResponse(['message' => 'Passwords do not match'], Response::HTTP_BAD_REQUEST);
            }

            // Met à jour le mot de passe via le service de sécurité
            $this->securityService->refreshPassword($data['newPassword']);

            return new JsonResponse(['message' => 'Password updated successfully'], Response::HTTP_OK);

        } catch (\Throwable $th) {
            // Gestion des erreurs et retour d'une réponse en cas d'échec
            return new JsonResponse(['message' => 'An error occurred while updating the password'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Envoie un lien de réinitialisation de mot de passe à l'utilisateur.
     * 
     * @Route("/sendPasswordLink", name="resetPassword", methods={"POST"})
     *
     * @param Request $request La requête HTTP contenant l'email de l'utilisateur et le lien de réinitialisation.
     * 
     * @return JsonResponse Réponse indiquant le succès ou l'échec de l'envoi du lien.
     */
    public function sendPasswordLink(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data['email']) || empty($data['link'])) {
                return new JsonResponse(['message' => 'Email or link cannot be empty'], Response::HTTP_BAD_REQUEST);
            }

            // Envoie le lien de réinitialisation de mot de passe via le service de sécurité
            $this->securityService->sendPasswordLink($data['email'], $data['link']);

            return new JsonResponse(['message' => 'Password reset link sent successfully'], Response::HTTP_OK);

        } catch (\Throwable $th) {
            // Gestion des erreurs et retour d'une réponse en cas d'échec
            return new JsonResponse(['message' => 'An error occurred while sending the password reset link'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
