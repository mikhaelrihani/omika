<?php

namespace App\Controller;

use App\Service\JwtTokenService;
use App\Service\MailerService;
use App\Service\SecurityService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SecurityController
 * 
 * This controller handles security operations such as user logout and password reset functionality.
 */
#[Route('/api/security', name: "app_security_")]
class SecurityController extends AbstractController
{
    private SecurityService $securityService;
    private MailerService $mailerService;

    /**
     * SecurityController constructor.
     *
     * @param SecurityService $securityService The service managing security-related logic.
     * @param MailerService $mailerService Service responsible for sending emails.
     */
    public function __construct(SecurityService $securityService, MailerService $mailerService)
    {
        $this->securityService = $securityService;
        $this->mailerService = $mailerService;
    }

    /**
     * Logs out the user by revoking their JWT refresh token.
     *
     * @Route("/logout", name="logout", methods={"POST"})
     *
     * @param Request $request The HTTP request.
     * @param JwtTokenService $jwtTokenService Service for handling JWT tokens.
     * 
     * @return JsonResponse A JSON response indicating the success of the logout operation.
     */
    #[Route('/logout', name: "logout", methods: ["POST"])]
    public function logout(Request $request, JwtTokenService $jwtTokenService): JsonResponse
    {
        // Retrieve the refresh token from the request and revoke it
        $refreshTokenToRevoke = $jwtTokenService->getRefreshTokenFromRequest($request);
        $jwtTokenService->revokeUserTokenAccess($refreshTokenToRevoke);

        // Respond with a successful logout message
        return new JsonResponse(['message' => 'Logged out successfully. Refresh token revoked'], Response::HTTP_OK);
    }

    /**
     * Updates the user's password.
     *
     * @Route("/setNewPassword", name="setNewPassword", methods={"POST"})
     * 
     * @return JsonResponse A response indicating the success or failure of the password update operation.
     */
    #[Route('/setNewPassword', name: "setNewPassword", methods: ["POST"])]
    public function setNewPassword(): JsonResponse
    {
        try {
            // Retrieve the user's email
            $email = $this->securityService->getEmail();

            // Validate and retrieve the new password
            $newPassword = $this->securityService->checkNewPassword();

            // Update the user's password
            $this->securityService->refreshPassword($email, $newPassword);

            return new JsonResponse(['message' => 'Password updated successfully'], Response::HTTP_OK);

        } catch (\Throwable $th) {
            // Handle any errors during the process
            return new JsonResponse(['message' => 'An error occurred while updating the password: ' . $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sends a password reset link to the user.
     *
     * @Route("/sendPasswordLink", name="sendPasswordLink", methods={"POST"})
     *
     * @param Request $request The HTTP request containing the user's email and the reset link.
     * 
     * @return JsonResponse A response indicating the success or failure of sending the reset link.
     */
    #[Route('/sendPasswordLink', name: "sendPasswordLink", methods: ["POST"])]
    public function sendPasswordLink(): JsonResponse
    {
        try {
            // Retrieve the user's email
            $email = $this->securityService->getEmail();
            $user = $this->securityService->getUserNotConnected();

            if ($user->isEnabled()) {
                // Generate the reset token and expiration date
                $token = $this->securityService->generateToken();
                $expiresAt = $this->securityService->getExpirationDate();

                // Save the password reset request with the token and email
                $this->securityService->savePasswordResetRequest($email, $token, $expiresAt);

                // Create the reset link
                $link = 'http://localhost:8080/api/security/reset-password?token=' . $token;

                // Define the email subject and body
                $subject = 'Renew your password';
                $body = 'Please click on this link to renew your password: <a href="' . htmlspecialchars($link) . '">Reset Password</a>';

                // Send the email
                $this->mailerService->sendEmailFacade($email, $subject, $body);

                return new JsonResponse(['message' => 'Password reset link sent successfully'], Response::HTTP_OK);
            } else {
                return new JsonResponse(['message' => 'User is not enabled'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Request error: ' . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception('Failed to send passwordLink email: ' . $e->getMessage());
        }
    }

    /**
     * Verifies the validity of a password reset token.
     *
     * @Route("/reset-password", name="reset-password", methods={"GET"})
     * 
     * @param Request $request The HTTP request containing the reset token as a query parameter.
     * 
     * @return JsonResponse A response indicating whether the token is valid or not.
     */
    #[Route('/reset-password', name: "reset-password", methods: ["GET"])]
    public function resetPassword(Request $request): JsonResponse
    {
        // Retrieve the token from the request's query parameters
        $token = $request->query->get('token');

        if (!$token) {
            return new JsonResponse(['message' => 'Token is missing'], Response::HTTP_BAD_REQUEST);
        }

        // Validate the token through the SecurityService
        if (!$this->securityService->validateToken($token)) {
            return new JsonResponse(['message' => 'Invalid or expired token'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['message' => 'Token is valid. Proceed with fetching setNewPassword route'], Response::HTTP_OK);
    }
}
