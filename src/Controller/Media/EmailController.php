<?php

namespace App\Controller\Media;

use App\Controller\BaseController;
use App\Service\EmailFacadeService;
use App\Service\MailerService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class EmailController
 * 
 * Contrôleur pour la gestion des emails dans l'API media.
 */
#[Route('api/media/email', name: "app_email_")]
class EmailController extends BaseController
{
    private EmailFacadeService $emailFacadeService;
    private MailerService $mailerService;

    /**
     * EmailController constructor.
     *
     * @param EmailFacadeService $emailFacadeService Service pour l'envoi d'emails de type "facade".
     * @param MailerService $mailerService Service de gestion d'envoi d'emails.
     */
    public function __construct(EmailFacadeService $emailFacadeService, MailerService $mailerService)
    {
        $this->emailFacadeService = $emailFacadeService;
        $this->mailerService = $mailerService;
    }

    /**
     * Route pour envoyer un email via le service MailerService.
     * 
     * @return Response Retourne un message indiquant le succès ou l'échec de l'envoi.
     */
    #[Route('/sendEmail', name: 'sendEmail', methods: ['POST'])]
    public function sendEmail(): Response
    {
        try {
            $this->mailerService->sendEmail();
            return new JsonResponse(['message' => 'Email sent successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to send email: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Route pour envoyer un email de bienvenue via le service EmailFacadeService.
     * 
     * @return Response Retourne un message indiquant le succès ou l'échec de l'envoi.
     */
    #[Route('/sendWelcomeEmail', name: 'sendWelcomeEmail', methods: ['POST'])]
    public function sendWelcomeEmail(): Response
    {
        try {
            $this->emailFacadeService->sendWelcomeEmail();
            return new JsonResponse(['message' => 'Welcome email sent successfully'], Response::HTTP_OK);
        }  catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to send welcome email: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
