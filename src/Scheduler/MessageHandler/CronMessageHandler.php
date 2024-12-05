<?php

namespace App\Scheduler\MessageHandler;

use App\Scheduler\Message\CronMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final class CronMessageHandler 
//! launch the command app:cronJob with : php bin/console messenger:consume scheduler_CronSchedule --vv
//! php bin/console messenger:consume scheduler_CleanupTokensSchedule scheduler_CronSchedule  -vv 
{
    public function __construct(private string $consolePath, private string $phpBinaryPath)
    {
    }

    public function __invoke(CronMessage $cronMessage): void
    {
        $process = new Process([$this->phpBinaryPath, $this->consolePath, 'app:cronJob']);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'Cron job execution failed with error: %s',
                $process->getErrorOutput()
            ));
        }


    }
}
