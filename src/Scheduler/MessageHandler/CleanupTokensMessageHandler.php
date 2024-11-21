<?php

namespace App\Scheduler\MessageHandler;

use App\Scheduler\Message\CleanupTokensMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final class CleanupTokensMessageHandler //!  php bin/console messenger:consume scheduler_CleanupTokensSchedule  -vv 
{
   

    public function __construct(private string $consolePath, private string $phpBinaryPath){ 
    }

    public function __invoke(CleanupTokensMessage $message): void
    {
        $process = new Process([$this->phpBinaryPath, $this->consolePath, 'app:cleanup-tokens']);
        $process->setTimeout(60); 
        $process->run();
        
    }
}
