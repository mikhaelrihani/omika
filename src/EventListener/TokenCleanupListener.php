<?php
namespace App\EventListener;

use App\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Psr\Log\LoggerInterface;

class TokenCleanupListener
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event)
    {
        // Compter le nombre de refresh tokens dans la base de données
        $count = $this->entityManager->getRepository(RefreshToken::class)->count([]);

        // Si le nombre de tokens dépasse 25, lancer la commande pour les nettoyer si ils ne sont plus valides
        // if ($count > 5) {
        //     $process = new Process(['C:\wamp64\bin\php\php8.2.18\php.exe', 'bin/console', 'gesdinet:jwt:clear']);
        //     $process->setWorkingDirectory('C:\wamp64\www\omika');
        //     $process->run();

        //     if ($process->isSuccessful()) {
        //         $this->logger->info('Refresh tokens cleanup successfully executed after authentication.', [
        //             'token_count' => $count,
        //             'output'      => $process->getOutput(),
        //         ]);
        //     } else {
        //         $this->logger->error('Failed to execute refresh tokens cleanup.', [
        //             'token_count'  => $count,
        //             'error_output' => $process->getErrorOutput(),
        //         ]);
        //     }
        // }
    }
}
