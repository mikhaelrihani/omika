<?php

namespace App\Service;

use Exception;

/**
 * Class EmailFacadeService
 * 
 * Service pour simplifier l'envoi d'emails via le MailerService.
 */
class EmailFacadeService
{
    private MailerService $mailerService;

    /**
     * EmailFacadeService constructor.
     *
     * @param MailerService $mailerService Service d'envoi d'emails.
     */
    public function __construct(MailerService $mailerService)
    {
        $this->mailerService = $mailerService;
    }

    /**
     * Envoie un email de bienvenue à l'utilisateur.
     *
     * @throws \RuntimeException Si aucune adresse email n'est fournie ou si des données requises manquent.
     * @throws Exception Si une erreur survient lors de l'envoi de l'email.
     */
    public function sendWelcomeEmail(): void
    {
        try {
            // Récupération des données nécessaires depuis la requête
            $requestBag = $this->mailerService->getRequest();

            $to = $requestBag->get('to');
            $username = $requestBag->get('username');

            // Validation des données
            if (null === $to) {
                throw new \RuntimeException('No receiver $to provided.');
            }
            if (null === $username) {
                throw new \RuntimeException('No username provided.');
            }

            $subject = 'Welcome to Omika';
            $body = 'Thank you for registering, ' . htmlspecialchars($username) . '!';

            $this->mailerService->sendEmailFacade($to, $subject, $body);

        } catch (\RuntimeException $e) {
            // Gestion d'erreurs spécifiques liées aux données manquantes
            throw new \RuntimeException('Request error: ' . $e->getMessage());
        } catch (Exception $e) {
            // Gestion d'erreurs lors de l'envoi de l'email
            throw new Exception('Failed to send welcome email: ' . $e->getMessage());
        }
    }
    public function sendPasswordLink(): void
    {
        //! mettre un time out sur la validitée du lien
        try {
            // Récupération des données nécessaires depuis la requête
            $request = $this->mailerService->getRequest();
            $jsonContent = $request->getContent();
            $data = json_decode($jsonContent, true);
            $link = $data[ "link" ];
            $email = $data[ "email" ];

            // Validation des données
            if (null ===  $email) {
                throw new \RuntimeException('No email provided.');
            }
            if (null === $link) {
                throw new \RuntimeException('No link provided.');
            }

            $subject = 'Renew your password';
            $body = 'Please click on this link to renew your password: <a href="' . htmlspecialchars($link) . '">Reset Password</a>';
            
            $this->mailerService->sendEmailFacade( $email, $subject, $body);

        } catch (\RuntimeException $e) {
            // Gestion d'erreurs spécifiques liées aux données manquantes
            throw new \RuntimeException('Request error: ' . $e->getMessage());
        } catch (Exception $e) {
            // Gestion d'erreurs lors de l'envoi de l'email
            throw new Exception('Failed to send welcome email: ' . $e->getMessage());
        }
    }
}
