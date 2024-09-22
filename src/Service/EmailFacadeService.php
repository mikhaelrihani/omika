<?php
namespace App\Service;


class EmailFacadeService
{
    private $mailerService;

    public function __construct(MailerService $mailerService)
    {
        $this->mailerService = $mailerService;
    }

    public function sendWelcomeEmail(): void
    {
        //$to = "mikabernik@gmail.com";
        //$username = "omika";
        $to = $this->mailerService->getRequestBag()->get("to");
        $username = $this->mailerService->getRequestBag()->get("username");
        
        $subject = 'Welcome to Omika';
        $body = 'Thank you for registering, ' . $username . '!';
        if ($to == null) {
            throw new \RuntimeException('No email provided');
        }

        $this->mailerService->sendEmailFacade($to, $subject, $body);

    }

}
