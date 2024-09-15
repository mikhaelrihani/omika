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
     * Valide le nouveau mot de passe fourni et vérifie qu'il correspond à la confirmation.
     * 
     * @param Request $request La requête contenant le nouveau mot de passe et sa confirmation.
     * 
     * @return JsonResponse|bool Retourne le mot de passe validé ou une réponse d'erreur si la validation échoue.
     */
    public function checkNewPassword(Request $request): JsonResponse|bool
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data[ 'newPassword' ], $data[ 'confirmPassword' ])) {
            return new JsonResponse(['message' => 'Missing required fields (newPassword or confirmPassword)'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data[ 'newPassword' ]) || empty($data[ 'confirmPassword' ])) {
            return new JsonResponse(['message' => 'Password cannot be empty'], Response::HTTP_BAD_REQUEST);
        }

        if ($data[ 'newPassword' ] !== $data[ 'confirmPassword' ]) {
            return new JsonResponse(['message' => 'Passwords do not match'], Response::HTTP_BAD_REQUEST);
        }

        return $data[ 'newPassword' ]; // Retourne le mot de passe validé
    }

    /**
     * Définit un nouveau mot de passe pour l'utilisateur.
     * 
     * @Route("/newPassword", name="newPassword", methods={"POST"})
     *
     * @param Request $request La requête contenant le nouveau mot de passe.
     * 
     * @return JsonResponse Réponse indiquant le succès ou l'échec de l'opération.
     */
    public function setNewPassword(Request $request): JsonResponse
    {
        try {
            // Valide le nouveau mot de passe
            $newPassword = $this->checkNewPassword($request);
            if ($newPassword instanceof JsonResponse) {
                return $newPassword; // En cas d'erreur dans la validation, retourne la réponse d'erreur
            }

            // Récupère l'email
            $email = $this->getEmail($request);
            if ($email instanceof JsonResponse) {
                return $email; // En cas d'erreur, retourne la réponse d'erreur
            }

            // Met à jour le mot de passe via le service de sécurité
            $this->securityService->refreshPassword($email, $newPassword);

            return new JsonResponse(['message' => 'Password updated successfully'], Response::HTTP_OK);

        } catch (\Throwable $th) {
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

            if (!isset($data[ 'link' ]) || empty($data[ 'link' ])) {
                return new JsonResponse(['message' => 'Link cannot be empty'], Response::HTTP_BAD_REQUEST);
            }

            $email = $this->getEmail($request);
            if ($email instanceof JsonResponse) {
                return $email; // Retourne la réponse d'erreur en cas de problème avec l'email
            }

            // Envoie le lien de réinitialisation de mot de passe via le service de sécurité
            $this->securityService->sendPasswordLink($email, $data[ 'link' ]);

            return new JsonResponse(['message' => 'Password reset link sent successfully'], Response::HTTP_OK);

        } catch (\Throwable $th) {
            return new JsonResponse(['message' => 'An error occurred while sending the password reset link'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère l'email à partir de la requête.
     * 
     * @param Request $request La requête contenant l'email.
     * 
     * @return string|JsonResponse Retourne l'email ou une réponse d'erreur si l'email est manquant ou invalide.
     */
    public function getEmail(Request $request): string|JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data[ 'email' ]) || !isset($data[ 'email' ])) {
            return new JsonResponse(['message' => 'Missing field email or email value is empty'], Response::HTTP_BAD_REQUEST);
        }

        return $data[ 'email' ];
    }
}
