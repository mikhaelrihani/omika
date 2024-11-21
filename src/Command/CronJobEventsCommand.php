<?php

namespace App\Command;

use App\Service\Event\CronService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:cronJob-events', //! php bin/console app:cronJob-events
    description: 'Handles events and tags daily update.'
)]
class CronJobEventsCommand extends Command
{

    public function __construct(protected CronService $cronService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Handles events and tags daily update');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $response = $this->cronService->load();

        if ($response->isSuccess()) {
            $todayEventsCount = $response->getData()[ 'todayEventsCount' ] ?? 0;
            $output->writeln("Total events processed for today: { $todayEventsCount}");

            return Command::SUCCESS;
        }

        $output->writeln('Error: ' . $response->getMessage());
        return Command::FAILURE;
    }
}
