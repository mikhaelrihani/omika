<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\event\Event;
use App\Entity\event\Section;
use App\Entity\event\EventTask;
use App\Entity\event\EventInfo;
use App\Entity\Event\EventRecurring;
use App\Entity\event\Issue;
use App\Entity\Event\MonthDay;
use App\Entity\Event\PeriodDate;
use App\Entity\Event\WeekDay;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use phpseclib3\Crypt\Random;

class EventFixtures extends BaseFixtures implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));

        // Créer les sections d'événements
        $this->createSections();
        $this->em->flush();
        // Créer les événements récurrence
        $this->createEventRecurring();
        $this->em->flush();
        // Créer les événements
        $this->createEvents(200);
        // Créer des Issues (ici en tant qu'exemple)
        $this->createIssues();
        $this->em->flush();
    }

    public function createSections(): void
    {
        $timestamps = $this->faker->createTimeStamps();

        $Sections = $this->faker->getSectionList()();
        $s = 0;
        foreach ($Sections as $section) {
            $Section = new Section();
            $Section->setName($section);
            $Section->setCreatedAt($timestamps[ 'createdAt' ]);
            $Section->setUpdatedAt($timestamps[ 'updatedAt' ]);
            $this->em->persist($Section);
            $this->addReference("section_{$s}", $Section);
            $s++;
        }

    }

    public function getEventBase(): Event
    {
        $sections = $this->retrieveEntities("section", $this);

        $event = new Event();
        $event
            ->setDescription($this->faker->sentence)
            ->setIsImportant($this->faker->boolean)
            ->setSide($this->faker->randomElement(['kitchen', 'office']));

        $section = new Section();
        $section->setName($sections[array_rand($sections)]);
        $event
            ->setSection($section);


        return $event;

    }

    public function setEventTaskOrInfo(DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt, int $activeDay = null): EventTask|EventInfo
    {
        $taskOrInfo = $this->faker->boolean;
        if ($taskOrInfo) {
            $task = new EventTask();
            $task
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            if ($activeDay < 0) {
                $task->setTaskStatus($this->faker->randomElement(['todo', 'done', 'pending','unrealised']));
            } elseif ($activeDay === 0) {
                $task->setTaskStatus($this->faker->randomElement(['todo', 'done', 'pending','warning','late']));

            } else {
                $task->setTaskStatus($this->faker->getOneRandomStatus());
            }

            return $task;
        } else {
            $info = new EventInfo();
            $info
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setInfo($this->faker->sentence);
            return $info;
        }
    }

    public function setEventRecurringTaskOrInfo(DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt, int $activeDay = null): EventTask|EventInfo
    {
    }

    public function createEventsfromRecurring(): void
    {
        $past_eventsRecurring = $this->retrieveEntities("past_eventRecurring", $this);
        $active_eventsRecurring = $this->retrieveEntities("active_eventRecurring", $this);
        $future_eventsRecurring = $this->retrieveEntities("future_eventRecurring", $this);

        $event = $this->getEventBase();
        $event
            ->setIsRecurring(True);


        foreach ($past_eventsRecurring as $eventRecurring) {
            $createdAtEventRecurring = $eventRecurring->getCreatedAt();
            $maxTimestamp = (new DateTimeImmutable(datetime: 'now'))->modify('-4 days')->format('Y-m-d H:i:s');
            $createdAt = $this->faker->dateTimeImmutableBetween($createdAtEventRecurring->format('Y-m-d H:i:s'), $maxTimestamp);
            $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), $maxTimestamp);
            $dueDate = $this->faker->dateTimeImmutableBetween(
                $createdAt,
                $updatedAt
            );
            $event
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setDateStatus("past")
                ->setActiveDay(null)
                ->setDueDate($dueDate);

            $eventRecurring->addEvent($event)
            ;


        }

        foreach ($active_eventsRecurring as $eventRecurring) {
            $createdAtEventRecurring = $eventRecurring->getCreatedAt();
            $maxTimestamp = (new DateTimeImmutable(datetime: 'now'))->modify('+7 days')->format('Y-m-d H:i:s');
            $createdAt = $this->faker->dateTimeImmutableBetween($createdAtEventRecurring->format('Y-m-d H:i:s'), $maxTimestamp);
            $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), $maxTimestamp);
            $dueDate = $this->faker->dateTimeImmutableBetween(
                $createdAt,
                $updatedAt
            );
            // Calcul de activeDay en fonction de l'écart de jours entre today et dueDate
            $activeday = (int) $dueDate->diff(new DateTimeImmutable('now'))->format('%r%a');

            $event
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setDateStatus("activeDayRange")
                ->setActiveDay($activeday)
                ->setDueDate($dueDate);

            $eventRecurring->addEvent($event);
        }

        foreach ($future_eventsRecurring as $eventRecurring) {
            $createdAtEventRecurring = $eventRecurring->getCreatedAt();
            $maxTimestamp = (new DateTimeImmutable(datetime: 'now'))->modify('+3 month')->format('Y-m-d H:i:s');
            $createdAt = $this->faker->dateTimeImmutableBetween($createdAtEventRecurring->format('Y-m-d H:i:s'), $maxTimestamp);
            $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), $maxTimestamp);

            $event
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setDateStatus("future")
                ->setActiveDay(null)
                ->setDueDate($dueDate);

            $eventRecurring->addEvent($event);
        }
        $this->em->persist($eventRecurring);
    }



    public function createEvents($numEvents): void
    {
        $users = $this->retrieveEntities("user", $this);
        $timestamps = $this->faker->createTimeStamps();
        $createdAt = $timestamps[ 'createdAt' ];
        $updatedAt = $timestamps[ 'updatedAt' ];
        $dueDate = $this->faker->dateTimeImmutableBetween(
            $createdAt,
            $updatedAt
        );
        $activeDayInt = $this->faker->
            // Limite $activeDayInt entre -3 et 7
            $activeDay = ($activeDayInt >= -3 && $activeDayInt <= 7) ? $activeDayInt : null;

        for ($e = 0; $e < $numEvents; $e++) {

            $event = $this->getEventBase();
            $event
                ->setIsRecurring(False)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setActiveDay($activeDay)
                ->setDueDate($dueDate);

            $this->em->persist($event);
        }
    }





    public function createEventRecurring(): void
    {
        // L'objectif des fixtures est de simuler un environnement de test réaliste, proche de la production. 
        // Pour cela, il est essentiel de générer des événements enfants pour chaque `EventRecurring`, répartis selon les fenêtres temporelles définies par `datestatus` : `past`, `activeDayRange`, et `future`.
        // Les timestamps de chaque `EventRecurring` serviront ainsi à horodater les événements enfants, en garantissant que ceux créés pendant la période active restent dans cette même période. Il en va de même pour les périodes *past* et *future*.
        // De cette manière, chaque événement enfant aura un `createdAt` supérieur à celui de son `EventRecurring` parent et respectera la règle `dueDate >= createdAt`.
        // Nous créons donc trois types d'`EventRecurring`, correspondant à chacun de ces statuts (`past`, `activeDayRange`, et `future`).

        // L'ordre chronologique des timestamps pour les périodes de `EventRecurring` doit être strictement respecté : `createdAt < periodeStart < updatedAt < periodeEnd`.


        //! Ici, la base de données sera capturée à la date de chargement des fixtures. Cette capture sera ensuite mise à jour par un cron job.
        //! Cependant, plus le chargement des fixtures s’éloigne de la date actuelle, moins les données du active day range seront pertinentes.
        //! Il est donc recommandé de recharger régulièrement les fixtures ou de créer un cron job pour recharger les fixtures, par exemple tous les trois jours.

        for ($e = 0; $e < 200; $e++) {

            // Initialisation des timestamps
            if ($e <= 40) {
                // EventRecurring dans le passé
                $createdAt = $this->faker->dateTimeImmutableBetween('-1 month', '-5 days');
                $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), '-4 days');
                $prefix = 'past_';
            } elseif (41 <= $e <= 151) {
                // EventRecurring dans la période active
                $createdAt = $this->faker->dateTimeImmutableBetween('-3 days', '+7 days');
                $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), 'now');
                $prefix = 'active_';
            } else {
                // EventRecurring dans le futur
                $createdAt = $this->faker->dateTimeImmutableBetween('+8 days', '+3 month');
                $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), '+6 days');
                $prefix = 'future_';
            }

            // Générer les dates de début et de fin de période
            $periodeStart = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), $updatedAt->format('Y-m-d H:i:s'));
            $periodeEnd = $this->faker->dateTimeImmutableBetween($updatedAt->format('Y-m-d H:i:s'), "+1 month");

            // Initialisation de l'EventRecurring
            $eventRecurring = new EventRecurring();
            $eventRecurring->setPeriodeStart($periodeStart)
                ->setPeriodeEnd($periodeEnd)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            // Déterminer si l'événement est quotidien ou non
            $everyday = rand(0, 1);
            if (!$everyday) {
                // Choisir au hasard une des trois récurrences : jours du mois, jours de la semaine, dates spécifiques
                $recurrenceType = rand(1, 3);
                $randomIndex = rand(1, 4);

                if ($recurrenceType === 1) {
                    // Jours du mois
                    $monthDays = [];
                    for ($i = 0; $i < $randomIndex; $i++) {
                        $randomDay = rand(1, 28);
                        if (!in_array($randomDay, $monthDays)) {
                            $monthday = new MonthDay();
                            $monthday->setDay($randomDay);
                            $eventRecurring->addMonthDay($monthday);
                            $monthDays[] = $randomDay;
                        }
                    }
                } elseif ($recurrenceType === 2) {
                    // Jours de la semaine
                    $weekDays = [];
                    for ($i = 0; $i < $randomIndex; $i++) {
                        $randomDay = rand(1, 7);
                        if (!in_array($randomDay, $weekDays)) {
                            $weekday = new WeekDay();
                            $weekday->setDay($randomDay);
                            $eventRecurring->addWeekDay($weekday);
                            $weekDays[] = $randomDay;
                        }
                    }
                } else {
                    // Dates spécifiques
                    $periodDates = [];
                    $randomIndex = rand(3, 10);
                    for ($i = 0; $i < $randomIndex; $i++) {
                        $randomDate = new DateTimeImmutable();
                        if (!in_array($randomDate, $periodDates)) {
                            $periodDate = new PeriodDate();
                            $periodDate->setDate($randomDate);
                            $eventRecurring->addPeriodDate($periodDate);
                            $periodDates[] = $randomDate;
                        }
                    }
                }
            } else {
                $eventRecurring->resetRecurringDays();
                $eventRecurring->setEveryday(true);
            }

            // Persister l'EventRecurring avec le préfixe de référence
            $this->em->persist($eventRecurring);
            $this->addReference("{$prefix}eventRecurring_{$e}", $eventRecurring);
        }


    }




    public function createIssues()
    {
        $users = $this->retrieveEntities("user", $this);
        $author = $this->faker->randomElement($users);

        $contacts = $this->retrieveEntities("contact", $this);
        $technicienContacts = [];
        foreach ($contacts as $contact) {
            if ($contact->getJob() == "technicien") {
                $technicienContacts[] = $contact;
            }
        }

        $countNumber = 0;
        $timeStamps = $this->faker->createTimeStamps();
        $createdAt = $timeStamps[ 'createdAt' ];


        for ($i = 0; $i < 30; $i++) {

            $technicienContacted = $this->faker->randomElement($technicienContacts);
            $technicienComing = $this->faker->randomElement($technicienContacts);

            $createdAt = (clone $createdAt)->modify('+' . $this->faker->numberBetween(0, 7) . ' days');
            $updatedAt = (clone $createdAt)->modify('+' . $this->faker->numberBetween(0, 7) . ' days');
            $fixDate = (clone $updatedAt)->modify('+' . $this->faker->numberBetween(0, 7) . ' days');
            // Génère une heure entre 06:00 et 18:00
            $hour = random_int(6, 18); // Heure minimale : 6h, Heure maximale : 18h
            $minute = random_int(0, 59); // Minutes entre 0 et 59
            // Formater l'heure générée
            $fixTime = sprintf('%02d:%02d', $hour, $minute);

            $issue = new Issue();
            $issue
                ->setCountNumber($countNumber + 1)
                ->setStatus($this->faker->getOneRandomStatus())
                ->setAuthor($author->getFullName())
                ->setTechnicianContacted($technicienContacted)
                ->setTechnicianComing($technicienComing)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setFixDate($fixDate)
                ->setFixTime(\DateTime::createFromFormat('H:i', $fixTime))
                ->setFollowUp($this->faker->numberBetween(1, 3))
                ->setSolution($this->faker->sentence())
                ->setSummary($this->faker->sentence())
                ->setDescription($this->faker->sentence());

            $this->em->persist($issue);
            $countNumber++;
        }

    }

    /**
     * Get the dependencies for this fixture.
     */
    public function getDependencies(): array
    {
        return [
            CarteFixtures::class,
            UserFixtures::class,
        ];
    }
}
