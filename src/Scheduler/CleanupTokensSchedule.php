<?php

namespace App\Scheduler;

use App\Scheduler\Message\CleanupTokensMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('CleanupTokensSchedule')]
class CleanupTokensSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::every('1 minute', new CleanupTokensMessage()) // Message à envoyer chaque minute
            );
    }
}


