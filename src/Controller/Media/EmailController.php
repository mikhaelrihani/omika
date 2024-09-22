<?php
namespace App\Controller\Media;

use App\Controller\BaseController;
use App\Service\EmailFacadeService;
use App\Service\MailerService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/media/email', name:"app_email_" )]

class EmailController extends BaseController
{
    private EmailFacadeService $emailFacadeService;
    private MailerService $mailerService;

    public function __construct(EmailFacadeService $emailFacadeService, MailerService $mailerService)
    {
        $this->emailFacadeService = $emailFacadeService;
        $this->mailerService = $mailerService;

    }
    
    #[Route('/sendEmail', name: 'sendEmail')]
    public function sendEmail(): Response
    {
        try {
            $this->mailerService->sendEmail();
            return new Response('Email sent successfully');
        } catch (\Exception $e) {
            return new Response('Failed to send email: ' . $e->getMessage());
        }
    }

    #[Route('/sendWelcomeEmail', name: 'sendWelcomeEmail')]
    public function sendWelcomeEmail(): Response
    {
        try {
            $this->emailFacadeService->sendWelcomeEmail();
            return new Response('Welcome email sent successfully', Response::HTTP_OK);
        } catch (\Exception $e) {
            return new Response('Failed to send welcome email: ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
