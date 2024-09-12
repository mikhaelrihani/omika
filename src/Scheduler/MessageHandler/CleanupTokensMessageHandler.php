<?php

namespace App\Scheduler\MessageHandler;

use App\Scheduler\Message\CleanupTokensMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final class CleanupTokensMessageHandler
{
    private string $consolePath;
    private string $phpBinaryPath;

    public function __construct(string $consolePath, string $phpBinaryPath)
    {
        $this->consolePath = $consolePath;
        $this->phpBinaryPath = $phpBinaryPath;
    }

    public function __invoke(CleanupTokensMessage $message): void
    {
        $process = new Process([$this->phpBinaryPath, $this->consolePath, 'app:cleanup-tokens']);
        $process->run();
    }
}
