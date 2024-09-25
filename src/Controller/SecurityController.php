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
    #[Route('/logout', name: "logout", methods: ["POST"])]
    public function logout(Request $request, JwtTokenService $jwtTokenService): JsonResponse
    {
        // Récupère le refresh token de la requête pour le révoquer
        $refreshTokenToRevoke = $jwtTokenService->getRefreshTokenFromRequest($request);
        $jwtTokenService->revokeUserTokenAccess($refreshTokenToRevoke);

        // Répond avec un succès de déconnexion
        return new JsonResponse(['message' => 'Logged out successfully. Refresh token revoked'], Response::HTTP_OK);
    }

    /**
     * Met à jour le mot de passe d'un utilisateur.
     *
     * @Route("/setNewPassword", name="setNewPassword", methods={"POST"})
     * 
     * @return JsonResponse Réponse indiquant le succès ou l'échec de l'opération de mise à jour du mot de passe.
     */
    #[Route('/setNewPassword', name: "setNewPassword", methods: ["POST"])]
    public function setNewPassword(): JsonResponse
    {
        try {
            // Récupère l'email de l'utilisateur
            $email = $this->securityService->getEmail();

            // Valide et récupère le nouveau mot de passe
            $newPassword = $this->securityService->checkNewPassword();

            // Met à jour le mot de passe de l'utilisateur
            $this->securityService->refreshPassword($email, $newPassword);

            return new JsonResponse(['message' => 'Password updated successfully'], Response::HTTP_OK);

        } catch (\Throwable $th) {
            // Gère toute erreur pendant le processus
            return new JsonResponse(['message' => 'An error occurred while updating the password: ' . $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Envoie un lien de réinitialisation de mot de passe à l'utilisateur.
     *
     * @Route("/sendPasswordLink", name="sendPasswordLink", methods={"POST"})
     *
     * @param Request $request La requête HTTP contenant l'email de l'utilisateur et le lien de réinitialisation.
     * 
     * @return JsonResponse Réponse indiquant le succès ou l'échec de l'envoi du lien.
     */
    #[Route('/sendPasswordLink', name: "sendPasswordLink", methods: ["POST"])]
    public function sendPasswordLink(Request $request): JsonResponse
    {
        try {
            // Récupère et valide les données de la requête
            $data = json_decode($request->getContent(), true);

            // Vérifie si le lien de réinitialisation est présent
            if (!isset($data['link']) || empty($data['link'])) {
                return new JsonResponse(['message' => 'Link cannot be empty'], Response::HTTP_BAD_REQUEST);
            }

            // Récupère l'email de l'utilisateur
            $email = $this->securityService->getEmail();
            if ($email instanceof JsonResponse) {
                return $email; // Retourne la réponse d'erreur si l'email est invalide
            }

            // Envoie le lien de réinitialisation de mot de passe
            $this->securityService->sendPasswordLink($email, $data['link']);

            return new JsonResponse(['message' => 'Password reset link sent successfully'], Response::HTTP_OK);

        } catch (\Throwable $th) {
            // Gère les erreurs liées à l'envoi du lien
            return new JsonResponse(['message' => 'An error occurred while sending the password reset link'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
