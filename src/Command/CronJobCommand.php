<?php

namespace App\Command;

use App\Service\Event\CronService as EventCronService;
use App\Service\Media\CronService as MediaCronService;
use App\Service\User\CronService as UserCronService;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:cronJob', //! php bin/console app:cronJob
    description: 'Handles app cron jobs.'
)]
class CronJobCommand extends Command
{

    public function __construct(
        protected EventCronService $eventCronService,
        protected UserCronService $userCronService,
        protected MediaCronService $mediaCronService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Handles app cron jobs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        // Forcer la définition de IS_CRON_JOB
        putenv('IS_CRON_JOB=true');
        $_ENV[ 'IS_CRON_JOB' ] = 'true';

        $output->writeln('Executing as a cron job.');
        $steps = [
            'responseEvent' => fn() => $this->eventCronService->load(),
            'responseUser'  => fn() => $this->userCronService->load(),
            'responseMedia' => fn() => $this->mediaCronService->load(),
        ];

        foreach ($steps as $stepName => $step) {
            try {
                $step();
            } catch (Exception $e) {

                $output->writeln("Step -{$stepName}- failed : .{$$stepName}->getMessage(), {$e->getMessage()}");
                return Command::FAILURE;
            }
        }

        $output->writeln('Cron job completed successfully');

        return Command::SUCCESS;
    }
}
