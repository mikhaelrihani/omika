<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\EventRecurring;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class EventRecurringService
{
    protected $now;
    protected $activeDayStart;
    protected $activeDayEnd;
    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em,
        protected EventService $eventService,
        protected ParameterBagInterface $parameterBag
    ) {
        $now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
        $activeDayStart = $this->parameterBag->get('active_day_start');
        $activeDayEnd = $this->parameterBag->get('active_day_end');
    }

    public function getParents(): array
    {
        $parents = $this->eventRecurringRepository->findAll();
        return $parents;
    }

    public function getChildrens(): array
    {
        $childrens = $this->eventRepository->findBy(["isRecurring" => "true"]);
        return $childrens;
    }

    public function handleRecurrenceType(EventRecurring $parent)
    {
        $recurrenceType = $parent->getRecurrenceType();
        match ($recurrenceType) {
            "monthDays" => $this->handleMonthDays($parent),
            "weekDays" => $this->handleWeekdays($parent),
            "periodDates" => $this->handlePeriodDates($parent),
            "everyday" => $this->handleEveryday($parent),
            default => throw new \Exception("Recurrence type not found")
        };
    }

    public function handleMonthDays(EventRecurring $parent): void
    {
        $monthDays = $parent->getMonthDays();

        [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);

        // Définir le mois de début de la période de vérification
        $currentMonthDate = $earliestCreationDate->modify('first day of this month');
        // Définir la fin de la période de vérification (le mois après latestCreationDate)
        $endPeriodDate = $latestCreationDate->modify('first day of next month');

        // Parcourir chaque mois dans la période définie
        while ($currentMonthDate <= $endPeriodDate) {
            foreach ($monthDays as $monthDay) {
                $day = $monthDay->getDay();

                // Générer la date pour ce jour spécifique dans le mois courant
                $dueDate = $currentMonthDate->modify("+{$day} days -1 day"); // -1 pour que "21" soit bien le 21e jour du mois

                // Vérifier si la dueDate est dans la période cible
                if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                    $this->createOneEverydayChild($parent, $dueDate);
                }
            }
            // Passer au mois suivant
            $currentMonthDate = $currentMonthDate->modify('first day of next month');
        }
    }
    public function handleWeekdays(EventRecurring $parent): void
    {
        $weekDays = $parent->getWeekDays();

        [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);

        // Définir le début de la période de vérification sur le lundi de la semaine du earliestCreationDate
        $currentWeekDate = $earliestCreationDate->modify('this week');

        // Parcourir chaque semaine dans la période définie
        while ($currentWeekDate <= $latestCreationDate) {
            foreach ($weekDays as $weekDay) {
                $day = $weekDay->getDay(); // Jour de la semaine (ex : 1 = Lundi, 2 = Mardi, etc.)

                // Calculer la date cible pour le jour de la semaine spécifié
                $dueDate = $currentWeekDate->modify("+{$day} days -1 day");

                // Vérifier si la dueDate est dans la période cible
                if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                    $this->createOneEverydayChild($parent, $dueDate);
                }
            }

            // Passer à la semaine suivante
            $currentWeekDate = $currentWeekDate->modify('next week');
        }
    }
    public function handlePeriodDates(EventRecurring $parent): void
    {
        [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);
        $dates = $parent->getPeriodDates();

        foreach ($dates as $date) {
            $dueDate = $date->getDate();

            if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                $this->createOneEverydayChild($parent, $dueDate);
            }
        }
    }

    public function handleEveryday(EventRecurring $parent): void
    {
        [$earliestCreationDate, $latestCreationDate] = $this->calculatePeriodLimits($parent);
        $diff = (int) $earliestCreationDate->diff($latestCreationDate)->format('%r%a');

        for ($i = 0; $i <= $diff; $i++) {
            $dueDate = $earliestCreationDate->modify("+{$i} day");

            if ($this->isWithinTargetPeriod($dueDate, $earliestCreationDate, $latestCreationDate)) {
                $this->createOneEverydayChild($parent, $dueDate);
            }
        }
    }


    private function isWithinTargetPeriod(DateTimeImmutable $dueDate, DateTimeImmutable $earliestCreationDate, DateTimeImmutable $latestCreationDate): bool
    {
        return $dueDate >= $earliestCreationDate && $dueDate <= $latestCreationDate;
    }

    private function calculatePeriodLimits(EventRecurring $parent): array
    {
        $latestDate = (new DateTimeImmutable($this->now->format('Y-m-d')))->modify("+{$this->activeDayEnd} day");
        $latestCreationDate = min($latestDate, $parent->getPeriodeEnd());

        $earliestCreationDate = max($this->now, $parent->getPeriodeStart());
        $earliestCreationDate = new DateTimeImmutable($earliestCreationDate->format('Y-m-d'));

        return [$earliestCreationDate, $latestCreationDate];
    }

    public function createOneEverydayChild(EventRecurring $parent, DateTimeImmutable $dueDate): void
    {
        $child = $this->setOneChildBase($parent);
        $child->setDueDate($dueDate);
        $this->setOneChildTimestamps($child);

        $users = $parent->getSharedWith();
        $status = $parent->getType() ? "todo" : null;
        $this->eventService->setRelations($child, $status, $users);

        $this->em->persist($child);
        $parent->addEvent($child);
        $this->em->flush();
    }

    public function setOneChildBase(EventRecurring $parent): Event
    {
        $child = (new Event())
            ->setDescription($parent->getDescription())
            ->setIsImportant(false)
            ->setSide($parent->getSide())
            ->setTitle($parent->getTitle())
            ->setCreatedBy($parent->getCreatedBy()->getFullName())
            ->setUpdatedBy($parent->getUpdatedBy()->getFullName())
            ->setType($parent->getType())
            ->setSection($parent->getSection())
            ->setIsRecurring(true);
        return $child;
    }

    public function setOneChildTimestamps(Event $child): void
    {
        $diff = (int) $this->now->diff($child->getDueDate())->format('%r%a');
        if ($diff >= $this->activeDayStart && $diff <= $this->activeDayEnd) {
            $dateStatus = "activeDayRange";
            $activeDay = $diff;
        } elseif ($diff >= -30 && $diff < $this->activeDayStart) {
            $dateStatus = "past";
            $activeDay = null;
        } else {
            $dateStatus = "future";
            $activeDay = null;
        }

        $child
            ->setActiveDay($activeDay)
            ->setDateStatus($dateStatus);

    }


}