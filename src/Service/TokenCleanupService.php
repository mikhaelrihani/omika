<?php

namespace App\Service;

use App\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\OutputInterface;

class TokenCleanupService
{
    private $entityManager;
    private $phpBinaryPath;
    private $consolePath;

    public function __construct(EntityManagerInterface $entityManager,$phpBinaryPath,$consolePath)
    {
        $this->entityManager = $entityManager;
        $this->phpBinaryPath = $phpBinaryPath;
        $this->consolePath = $consolePath;
    }

    public function performCleanup(OutputInterface $output)
    {
        // Compter le nombre de refresh tokens dans la base de données
        $count = $this->entityManager->getRepository(RefreshToken::class)->count([]);
    
        // Si le nombre de refresh tokens est supérieur à 5, exécutez la commande de nettoyage
        if ($count > 5) {
            $process = new Process([$this->phpBinaryPath, $this->consolePath, 'gesdinet:jwt:clear']);
            $process->run();
    
            if ($process->isSuccessful()) {
                // Afficher un message en console pour succès
                $output->writeln('Cleanup tokens command executed successfully. Token count: ' . $count);
                $output->writeln('Output: ' . $process->getOutput());
            } else {
                // Afficher un message d'erreur en console
                $output->writeln('Failed to execute cleanup tokens command. Token count: ' . $count);
                $output->writeln('Error output: ' . $process->getErrorOutput());
                return; // Sortir de la fonction en cas d'échec
            }
        } else {
            // Si le nombre de tokens est inférieur ou égal à 5
            $output->writeln('No need to clean up tokens, count is below or equal to 5.');
        }
    }
    
}
