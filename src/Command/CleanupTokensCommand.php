<?php

namespace App\Command;

use App\Service\TokenCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:cleanup-tokens', 
    description: 'Nettoie les tokens obsolètes.'
)]
class CleanupTokensCommand extends Command
{
 
    private $tokenCleanupService;

    public function __construct(TokenCleanupService $tokenCleanupService)
    {
        parent::__construct();
        $this->tokenCleanupService = $tokenCleanupService;
    }

    protected function configure()
    {
        $this
            ->setDescription('Cleans up old refresh tokens if their number exceeds a certain threshold.')
            ->setHelp('This command allows you to clean up old refresh tokens in the database.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->tokenCleanupService->performCleanup($output);
            $output->writeln('Token cleanup executed successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('Token cleanup failed.');
            $output->writeln('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
}
