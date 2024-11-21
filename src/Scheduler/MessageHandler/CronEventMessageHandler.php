<?php

namespace App\Scheduler\MessageHandler;

use App\Scheduler\Message\CronEventMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final class CronEventMessageHandler 
//! launch the command app:cronJob-events with : php bin/console messenger:consume scheduler_CronEvent --vv
//! php bin/console messenger:consume scheduler_CleanupTokensSchedule scheduler_CronEvent  -vv 
{
    public function __construct(private string $consolePath, private string $phpBinaryPath)
    {
    }

    public function __invoke(CronEventMessage $cronEventMessage): void
    {
        $process = new Process([$this->phpBinaryPath, $this->consolePath, 'app:cronJob-events']);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'Cron job execution failed with error: %s',
                $process->getErrorOutput()
            ));
        }


    }
}
