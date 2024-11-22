<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-timezone',
    description: 'Test global timezone configuration',
)]
//! php bin/console app:test-timezone

class TestTimezoneCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timezone = date_default_timezone_get();
        $output->writeln('Default timezone: ' . $timezone);

        $date = new \DateTime();
        $output->writeln('Current datetime: ' . $date->format('Y-m-d H:i:s T'));

        return Command::SUCCESS;
    }
}
