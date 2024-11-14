<?php

namespace App\Service\Cron;

class CronCreateService extends CronBaseService
{
    public function load()
    {
        $eventRecurringParents = $this->getEventRecurringParents();
        $this->create($eventRecurringParents);
    }

    public function create(array $eventRecurringParents)
    {
        foreach ($eventRecurringParents as $eventRecurring) {
            $this->eventRecurringService->handleRecurrenceType($eventRecurring);
        };
    }

}