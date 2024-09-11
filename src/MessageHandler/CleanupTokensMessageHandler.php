<?php

namespace App\MessageHandler;

use App\Message\CleanupTokensMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class CleanupTokensMessageHandler
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(CleanupTokensMessage $message)
    {
        // Chemin vers l'exécutable PHP
        $phpBinaryPath = PHP_BINARY;

        // Chemin vers le script console de Symfony
        $consolePath = __DIR__ . '/../../bin/console';

        // Créez une instance de Process avec la commande à exécuter
        $process = new Process([$phpBinaryPath, $consolePath, 'app:cleanup-tokens']);
        $process->run();

        // Vérifiez si le processus a réussi
        if ($process->isSuccessful()) {
            $this->logger->info('Cleanup tokens command executed successfully.');
        } else {
            $this->logger->error('Failed to execute cleanup tokens command.', [
                'error' => $process->getErrorOutput(),
            ]);
        }
    }
}
