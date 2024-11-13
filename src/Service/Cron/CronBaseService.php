<?php

namespace App\Service\Cron;

use App\Entity\Event\EventRecurring;
use App\Repository\Event\EventRecurringRepository;
use App\Repository\Event\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

class CronBaseService
{
    public function __construct(
        protected EventRecurringRepository $eventRecurringRepository,
        protected EventRepository $eventRepository,
        protected EntityManagerInterface $em
    ) {

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

    public function handleEventRecurringParents()
    {
        $eventRecurringParents = $this->getEventRecurringParents();
        foreach ($eventRecurringParents as $eventRecurring) {
            $recurrenceType = $eventRecurring->getRecurrenceType();
            match ($recurrenceType) {
                "monthDays" => $this->handleMonthDays($eventRecurring),
                "weekDays" => $this->handleWeekdays($eventRecurring),
                "periodDates" => $this->handlePeriodDates($eventRecurring),
                "everyday" => $this->handleEveryday($eventRecurring),
                default => throw new \Exception("Recurrence type not found")
            };
        }
    }

    public function handleEveryday(EventRecurring $eventRecurring): void
    {
        $this->createEverydayChildren($eventRecurring);
    }

    private function createEverydayChildren(EventRecurring $eventRecurring): void
    {
        $originalEvent = $this->setEventBase();
        $this->em->persist($originalEvent);

        for ($i = 0; $i < $numberOfEventsChildren; $i++) {
            $event = ($i === 0) ? $originalEvent : $this->duplicateEventBase($originalEvent);
            $dueDate = $firstDueDate->modify("+{$i} days");
            $this->setRecurringChildrensTimestamps($event, $dueDate, $updatedAtParent, $now);
            $this->setRelations($event, $eventRecurring);
        }
    }

    public function setRecurringEventBase(EventRecurring $eventRecurring): Event
    {
        $createdBy =$eventRecurring->getCreatedBy();
        $updatedBy = $eventRecurring->getUpdatedBy();

        $sections = $this->retrieveEntities("section", $this);
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if (empty($sections)) {
            $sections = $this->em->getRepository(Section::class)->findAll();
        }
        $section = $sections[array_rand($sections)];

        $event = new Event();
        $event
            ->setDescription($this->faker->sentence)
            ->setIsImportant($this->faker->boolean)
            ->setSide($this->faker->randomElement(['kitchen', 'office']))
            ->setTitle($this->faker->sentence)
            ->setCreatedBy($createdBy)
            ->setUpdatedBy($updatedBy)
            ->setType($this->faker->randomElement(['task', 'info']))
            ->setSection($section);

        return $event;
    }
//! ----------------------------------------------------------------
    public function handlePeriodDates(EventRecurring $eventRecurring): void
    {
        foreach ($data[ "periodDates" ] as $periodDate) {
            $dueDate = $periodDate->getDate();
            $diff = (int) $data[ "now" ]->diff($dueDate)->format('%r%a');
            // Check active range validity
            if ($diff > 7 || $diff < -30) {
                continue; // Exclude dates outside of active range
            } else {
                $this->createChildren($eventRecurring, $dueDate, $data[ "updatedAtParent" ], $data[ "now" ]);

            }
        }
    }
   
    public function handleMonthDays(EventRecurring $eventRecurring): void
    {
        // Définir le mois de début de la période de vérification
        $currentMonthDate = ($data[ "earliestCreationDate" ])->modify('first day of this month');

        // Définir la fin de la période de vérification (le mois après latestCreationDate)
        $endPeriodDate = ($data[ "latestCreationDate" ])->modify('first day of next month');

        // Parcourir chaque mois dans la période définie
        while ($currentMonthDate <= $endPeriodDate) {
            foreach ($data[ "monthDays" ] as $monthDay) {
                $day = $monthDay->getDay(); // Jour spécifique dans le mois (ex : 13, 21, 27)

                // Générer la date pour ce jour spécifique dans le mois courant
                $dueDate = $currentMonthDate->modify("+{$day} days -1 day"); // -1 pour que "21" soit bien le 21e jour du mois

                // Vérifier si la dueDate est dans la période cible
                if ($dueDate >= $data[ "earliestCreationDate" ] && $dueDate <= $data[ "latestCreationDate" ]) {
                    // Créer l'événement enfant pour ce jour et ce mois spécifiés

                    $this->createChildren($eventRecurring, $dueDate, $data[ "updatedAtParent" ], $data[ "now" ]);
                }
            }

            // Passer au mois suivant
            $currentMonthDate = $currentMonthDate->modify('first day of next month');
        }
    }
   
    public function handleWeekdays( EventRecurring $eventRecurring): void
    {
        // Définir le début de la période de vérification sur le lundi de la semaine du earliestCreationDate
        $currentWeekDate = $data[ "earliestCreationDate" ]->modify('this week');

        // Parcourir chaque semaine dans la période définie
        while ($currentWeekDate <= $data[ "latestCreationDate" ]) {
            foreach ($data[ "weekDays" ] as $weekDay) {
                $day = $weekDay->getDay(); // Jour de la semaine (ex : 1 = Lundi, 2 = Mardi, etc.)

                // Calculer la date cible pour le jour de la semaine spécifié
                $dueDate = $currentWeekDate->modify("+{$day} days -1 day");

                // Vérifier si la dueDate est dans la période cible
                if ($dueDate >= $data[ "earliestCreationDate" ] && $dueDate <= $data[ "latestCreationDate" ]) {
                    // Créer l'événement enfant pour ce jour et cette semaine spécifiés
                    $this->createChildren($eventRecurring, $dueDate, $data[ "updatedAtParent" ], $data[ "now" ]);
                }
            }

            // Passer à la semaine suivante
            $currentWeekDate = $currentWeekDate->modify('next week');
        }
    }

}