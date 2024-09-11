<?php

namespace App\Scheduler\MessageHandler;

use App\Scheduler\Message\CleanupTokensMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
final class CleanupTokensMessageHandler
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(CleanupTokensMessage $message): void
    {
        // Chemin vers l'exécutable PHP et la console de Symfony
        $phpBinaryPath = 'C:\\wamp64\\bin\\php\\php8.2.18\\php.exe';
        $consolePath = 'C:\\wamp64\\www\\omika\\bin\\console';

        // Commande à exécuter
        $process = new Process([$phpBinaryPath, $consolePath, 'gesdinet:jwt:clear']);

        try {
            $process->run();

            // Vérifie si le processus a échoué
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Log en cas de succès
            $this->logger->info('Cleanup tokens command executed successfully.', [
                'output' => $process->getOutput(),
            ]);
        } catch (ProcessFailedException $e) {
            // Log en cas d'erreur détaillée
            $this->logger->error('Failed to execute cleanup tokens command.', [
                'error' => $e->getMessage(),
                'process_output' => $process->getErrorOutput(),
            ]);
        } catch (\Exception $e) {
            // Log pour toute autre erreur
            $this->logger->error('An unexpected error occurred.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
