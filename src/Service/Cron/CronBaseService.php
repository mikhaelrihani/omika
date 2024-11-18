<?php

namespace App\Service\Cron;

use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use App\Service\Event\EventRecurringService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CronBaseService
{
    protected $now;
    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventRecurringService $eventRecurringService,
        protected ParameterBagInterface $parameterBag
    ) {
        $now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
    }

    public function getEventRecurringParents(): array
    {
        $eventRecurringParents = $this->eventRecurringRepository->findAll();
        return $eventRecurringParents;
    }

    public function getEventRecurringChildrens(): array
    {
        $eventRecurringChildrens = $this->eventRepository->findBy(["isRecurring" => "true"]);
        return $eventRecurringChildrens;
    }

    

}