<?php

namespace App\Service;

use App\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

class TokenCleanupService
{
    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function performCleanup()
    {
        // Compter le nombre de refresh tokens dans la base de données
        $count = $this->entityManager->getRepository(RefreshToken::class)->count([]);

        // Si le nombre de tokens dépasse 5, lancer la commande pour les nettoyer si ils ne sont plus valides
        if ($count > 5) {
            // Chemin vers l'exécutable PHP
            $phpBinaryPath = 'C:\\wamp64\\bin\\php\\php8.2.18\\php.exe';

            // Chemin vers le script console de Symfony
            $consolePath = 'C:\\wamp64\\www\\omika\\bin\\console';

            // Créez une instance de Process avec les chemins complets et les arguments
            $process = new Process([$phpBinaryPath, $consolePath, 'gesdinet:jwt:clear']);

            $process->run();

            if ($process->isSuccessful()) {
                $this->logger->info('Refresh tokens cleanup successfully executed.', [
                    'token_count' => $count,
                    'output'      => $process->getOutput(),
                ]);
            } else {
                $this->logger->error('Failed to execute refresh tokens cleanup.', [
                    'token_count'  => $count,
                    'error_output' => $process->getErrorOutput(),
                ]);
            }
        }
    }
}
