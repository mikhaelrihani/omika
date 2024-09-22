<?php
namespace App\Service;

use Exception;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;

class MailerService
{

    private $from;
    private $mailer;

    private $requestStack;

    public function __construct(TransportInterface $transport, string $from, RequestStack $requestStack)
    {
        $this->mailer = $transport;
        $this->from = $from;
        $this->requestStack = $requestStack;
    }

    public function getEmailData(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new \RuntimeException('No current request available');
        }

        $to = $request->request->get('to');
        $subject = $request->request->get('subject');
        $body = $request->request->get('body');
        $file = $request->request->get('file');

        $emailData = ["to" => $to, "subject" => $subject, "body" => $body, "file" => $file];
        return $emailData;

    }


    public function sendEmail(): ?SentMessage
    {
        $emailData = $this->getEmailData();
        if (!$emailData) {
            throw new Exception;
        }
        $email = (new Email())
            ->from($this->from)
            ->to($emailData[ "to" ])
            ->subject($emailData[ "subject" ])
            ->html($emailData[ "body" ])
            ->attach($emailData[ "file" ]);

        try {
            return $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new Exception('Failed to send email: ' . $e->getMessage());
        }

    }

    public function sendEmailFacade($to, $subject, $body, $file = null): ?SentMessage
    {

        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject($subject)
            ->html($body)
            ->attach($file);

        try {
            return $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new Exception('Failed to send email: ' . $e->getMessage());
        }

    }

    public function getRequestBag()
    {
        return $this->requestStack->getCurrentRequest()->request;
    }
}
