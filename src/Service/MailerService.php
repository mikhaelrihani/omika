<?php

namespace App\Service;

use Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;


/**
 * Class MailerService
 * 
 * Service pour la gestion de l'envoi d'emails via l'API.
 */
class MailerService
{
    private string $from;
    private TransportInterface $mailer;
    private RequestStack $requestStack;

    /**
     * MailerService constructor.
     *
     * @param TransportInterface $transport Instance de transport pour l'envoi des emails.
     * @param string $from Adresse email d'envoi par défaut.
     * @param RequestStack $requestStack Pile de requêtes pour récupérer les données de la requête en cours.
     */
    public function __construct(TransportInterface $transport, string $from, RequestStack $requestStack)
    {
        $this->mailer = $transport;
        $this->from = $from;
        $this->requestStack = $requestStack;
    }

    /**
     * Récupère les données nécessaires à l'envoi de l'email depuis la requête actuelle.
     *
     * @return array Données de l'email : to, subject, body, et file.
     * @throws \RuntimeException Si aucune requête n'est disponible.
     */
    public function getEmailData(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new \RuntimeException('No current request available');
        }

        $to = $request->request->get('to');
        $subject = $request->request->get('subject');
        $body = $request->request->get('body');

        // Gestion d'erreurs si des champs requis sont manquants
        if (!$to || !$subject || !$body) {
            throw new Exception('Missing required email data (to, subject, body).');
        }

        // Récupération du fichier en tant qu'UploadedFile, ou null si aucun fichier n'a été envoyé
        $file = $request->files->get('file');

        return [
            "to" => $to,
            "subject" => $subject,
            "body" => $body,
            "file" => $file
        ];
    }

    /**
     * Envoie un email avec les données récupérées de la requête.
     *
     * @return SentMessage|null Message envoyé ou null en cas d'échec.
     * @throws Exception Si des erreurs surviennent lors de la préparation ou de l'envoi de l'email.
     */
    public function sendEmail(): ?SentMessage
    {
        try {
            $emailData = $this->getEmailData();
        } catch (\RuntimeException $e) {
            throw new Exception('Error retrieving request data: ' . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception('Error processing email data: ' . $e->getMessage());
        }

        try {
            $email = (new Email())
                ->from($this->from)
                ->to($emailData["to"])
                ->subject($emailData["subject"])
                ->html($emailData["body"]);

            // Si un fichier a été envoyé, on l'attache à l'email
            if ($emailData["file"] instanceof UploadedFile) {
                $file = $emailData["file"];

                // Attacher le fichier avec le bon nom et type MIME
                $email->attach(
                    file_get_contents($file->getPathname()), // Lire le contenu du fichier
                    $file->getClientOriginalName(),           // Nom d'origine du fichier
                    $file->getMimeType()                      // Type MIME du fichier
                );
            }

            return $this->mailer->send($email);

        } catch (TransportExceptionInterface $e) {
            throw new Exception('Failed to send email: ' . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception('An error occurred while attaching the file: ' . $e->getMessage());
        }
    }

    /**
     * Méthode façade pour envoyer un email simple sans pièce jointe.
     *
     * @param string $to Destinataire.
     * @param string $subject Sujet de l'email.
     * @param string $body Contenu HTML du corps de l'email.
     * @return SentMessage|null Message envoyé ou null en cas d'échec.
     * @throws Exception Si l'envoi échoue.
     */
    public function sendEmailFacade(string $to, string $subject, string $body): ?SentMessage
    {
        try {
            $email = (new Email())
                ->from($this->from)
                ->to($to)
                ->subject($subject)
                ->html($body);

            return $this->mailer->send($email);

        } catch (TransportExceptionInterface $e) {
            throw new Exception('Failed to send email: ' . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception('An error occurred while sending the email: ' . $e->getMessage());
        }
    }

    /**
     * Récupère le request bag de la requête actuelle.
     *
     * @return \Symfony\Component\HttpFoundation\ParameterBag|null Bag des paramètres de la requête.
     * @throws \RuntimeException Si aucune requête n'est disponible.
     */
    public function getRequestBag()
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new \RuntimeException('No current request available.');
        }

        return $request->request;
    }
}
