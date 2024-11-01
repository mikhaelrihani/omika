<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\event\Event;
use App\Entity\event\Section;
use App\Entity\event\EventTask;
use App\Entity\event\EventInfo;
use App\Entity\Event\EventRecurring;
use App\Entity\Event\EventSharedInfo;
use App\Entity\event\Issue;
use App\Entity\Event\MonthDay;
use App\Entity\Event\PeriodDate;
use App\Entity\Event\WeekDay;
use App\Service\EventDuplicationService;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;


class EventFixtures extends BaseFixtures implements DependentFixtureInterface
{


    public function __construct(protected EventDuplicationService $eventDuplicationService)
    {

    }

    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));

        // Créer des Issues (ici en tant qu'exemple)
        $this->createIssues();
        $this->em->flush();
        // Créer les sections d'événements
        $this->createSections();
        $this->em->flush();
        // Créer les événements
        $this->createEventsActiveDayRange(200);
        $this->em->flush();
        // Créer les événements récurrence parents
        $this->createEventRecurringParent();
        $this->em->flush();
        // Créer les événements enfants pour chaque événement récurrence parent
        $this->createEventsChildrenforEachEventRecurringParent();
        $this->em->flush();

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


    public function createEventsActiveDayRange($numEvents): void
    {
        for ($e = 0; $e < $numEvents; $e++) {
            $timestamps = $this->faker->createTimeStamps();
            $createdAt = $timestamps[ 'createdAt' ];
            $updatedAt = $timestamps[ 'updatedAt' ];
            $dueDate = $this->faker->dateTimeImmutableBetween(
                $createdAt,
                $updatedAt
            );
            $activeDayInt = (int) $dueDate->diff(new DateTimeImmutable('now'))->format('%r%a');
            // Limite $activeDayInt entre -3 et 7
            $activeDay = ($activeDayInt >= -3 && $activeDayInt <= 7) ? $activeDayInt : null;

            $event = $this->setEventBase();
            $event
                ->setIsRecurring(False)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setActiveDay($activeDay)
                ->setDueDate($dueDate)
                ->setDateStatus("activeDayRange");

            $this->setEventType($event);
            $this->em->persist($event);
        }
    }

    public function createEventRecurringParent(): void
    {
        // The goal of the fixtures is to simulate a realistic test environment close to production.
        // It's essential to generate child events for each `EventRecurring`, distributed according to time windows defined by `datestatus`: `past`, `activeDayRange`, and `future`.
        // Each `EventRecurring` timestamp will timestamp child events, ensuring that those created during the active period remain within this same period.
        // This guarantees that each child event has a `createdAt` greater than its `EventRecurring` parent and respects the rule `dueDate >= createdAt`.
        // We create three types of `EventRecurring`, corresponding to these statuses (`past`, `activeDayRange`, and `future`).

        // The chronological order of timestamps for `EventRecurring` periods must strictly be: `createdAt < periodeStart < updatedAt < periodeEnd`.

        // The database will be captured at the time of loading the fixtures. This capture will then be updated by a cron job.
        // However, the further the loading of fixtures is from the current date, the less relevant the active day range data will be.
        // It is recommended to regularly reload the fixtures or create a cron job to reload them, for example every three days.

        for ($e = 0; $e < 10; $e++) {
            $timeStamps = $this->faker->createTimeStamps();
            $createdAt = $timeStamps[ 'createdAt' ];
            $updatedAt = $timeStamps[ 'updatedAt' ];

            // Generate start and end period dates
            $periodeStart = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), $updatedAt->format('Y-m-d H:i:s'));
            $periodeEnd = $this->faker->dateTimeImmutableBetween($updatedAt->format('Y-m-d H:i:s'), "+1 month");

            // Initialize the EventRecurring
            $eventRecurring = new EventRecurring();
            $eventRecurring
                ->setPeriodeStart($periodeStart)
                ->setPeriodeEnd($periodeEnd)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            // Determine if the event is daily or not
            if (rand(0, 1)) {
                $eventRecurring->resetRecurringDays();
                $eventRecurring->setEveryday(true);
            } else {
                // Randomly choose one of three recurrence types: month days, week days, specific dates
                $recurrenceType = rand(1, 3);
                $randomIndex = rand(1, 4);

                switch ($recurrenceType) {
                    case 1: // Days of the month
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
                        break;

                    case 2: // Days of the week
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
                        break;

                    case 3: // Specific dates
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
                        break;
                }
            }

            $this->em->persist($eventRecurring);
            $this->addReference("eventRecurring_{$e}", $eventRecurring);
        }
    }


    public function createEventsChildrenforEachEventRecurringParent(): void
    {
        $eventsRecurring = $this->retrieveEntities("eventRecurring", $this);

        foreach ($eventsRecurring as $eventRecurring) {
            // Retrieve properties from the parent event
            $periodDates = $eventRecurring->getPeriodDates();
            $monthDays = $eventRecurring->getMonthDays();
            $weekDays = $eventRecurring->getWeekDays();
            $everyday = $eventRecurring->isEveryday();

            $now = new DateTimeImmutable('now');
            $startDate = $eventRecurring->getPeriodeStart();
            $endDate = $eventRecurring->getPeriodeEnd();
            $createdAtParent = $updatedAtParent = $eventRecurring->getCreatedAt();

            // Filter for events within the active range (+7 to -30 days from now)
            $firstDueDate = $startDate;
            $lastDueDate = $endDate;
            $latestCreationDate = $now->modify('+7 days');
            $earliestCreationDate = $now->modify('-30 days');

            // Skip if no creation is needed
            if ($firstDueDate > $latestCreationDate || $earliestCreationDate > $latestCreationDate) {
                continue; // No child events to create
            }

            // Handle everyday events
            if ($everyday) {
                $numberOfEventsChildren = $this->calculateEverydayChildren($firstDueDate, $lastDueDate, $latestCreationDate, $endDate);
                $this->createChildEvents($eventRecurring, $firstDueDate, $numberOfEventsChildren, $createdAtParent, $now);

            }

            // Handle period dates
            if ($periodDates) {
                foreach ($periodDates as $periodDate) {
                    $dueDate = $periodDate->getDate();
                    $this->handlePeriodDate($eventRecurring, $dueDate, $createdAtParent, $now);
                }
            }

            // Handle month days
            if ($monthDays) {
                foreach ($monthDays as $monthDay) {
                    $day = $monthDay->getDay();
                    $date = (new DateTimeImmutable("now"))->modify("first day of this month")->modify("+{$day} days");
                    $this->handleMonthDay($eventRecurring, $date, $createdAtParent, $now);
                }
            }

            // Handle week days
            if ($weekDays) {
                foreach ($weekDays as $weekDay) {
                    $day = $weekDay->getDay();
                    $date = (new DateTimeImmutable("now"))->modify("this week")->modify("+{$day} days");
                    $this->handleWeekDay($eventRecurring, $date, $createdAtParent, $now);
                }
            }
        }
    }



    public function duplicateEvent($event): Event
    {
        $dueDate = $event->getDueDate()->modify('+1 day');
        $createdAt = $event->getCreatedAt()->modify('+1 day');
        $updatedAt = $event->getUpdatedAt()->modify('+1 day');
        $now = new DateTimeImmutable('now');
        $dueDateDiff = $dueDate->diff($now)->format('%r%a');

        if ($dueDateDiff >= 30 && $dueDateDiff < -3) {
            $dateStatus = "past";
            $activeDay = null;
        } elseif ($dueDateDiff > 7) {
            $dateStatus = "future";
            $activeDay = null;
        } else {
            $dateStatus = "activeDayRange";
            $activeDay = (int) $dueDateDiff;
        }
        $newEvent = $this->eventDuplicationService->duplicateEventProperties($event);
        $newEvent
            ->setDateStatus($dateStatus)
            ->setActiveDay($activeDay)
            ->setDueDate($dueDate)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt);


        return $newEvent;
    }

    public function duplicatePendingEvent(Event $event): void
    {
        $numberOfPendingDays = $this->faker->numberBetween(1, 7);
        $i = 1;
        while ($i <= $numberOfPendingDays) {
            $newEvent = $this->duplicateEvent($event);
            if ($i === $numberOfPendingDays) {
                $this->setEventTask($newEvent, 'done');
                $this->em->persist($newEvent);
            } else {
                $this->setEventTask($newEvent, 'pending');
                $this->em->persist($newEvent);

            }
            $newEvent->getEventRecurring()->addEvent($newEvent);
            // on flushe a chaque event pour pouvoir traiter les status unrealised de chaque event enfant dans la periode -30 a -1.
            $this->em->flush();

            // Met à jour l'événement courant pour la prochaine itération
            $event = $newEvent;
            $i++;
        }
    }


    public function duplicatePendingRecurringEvent(Event $event)
    {
        $eventParent = $event->getEventRecurring();
        $eventsBrothers = $eventParent->getEvents();

        // Initialise la date de l'événement courant et le jour suivant
        $currentDueDate = $event->getDueDate();
        $nextDueDate = $currentDueDate->modify('+1 day');

        // Vérifie si un frère existe le jour suivant
        $hasBrotherNextDay = $eventsBrothers->exists(function ($key, $eventBrotherNextDay) use ($nextDueDate) {
            return $eventBrotherNextDay->getDueDate() == $nextDueDate;
        });

        // Si un frère est trouvé le jour suivant, on marque l'événement actuel comme "unrealised"
        if ($hasBrotherNextDay) {
            $this->setEventTask($event, 'unrealised');
        } else {
            // Sinon, on commence à créer des événements duplicata avec le statut "pending"
            $numberOfPendingDays = $this->faker->numberBetween(1, 7);
            $i = 1;

            while ($i <= $numberOfPendingDays) {
                $newEvent = $this->duplicateEvent($event);

                // Vérifie encore une fois pour chaque nouvel événement si un frère est présent le jour suivant
                $nextDueDate = $newEvent->getDueDate()->modify('+1 day');
                $hasBrotherNextDay = $eventsBrothers->exists(function ($key, $eventBrotherNextDay) use ($nextDueDate) {
                    return $eventBrotherNextDay->getDueDate() == $nextDueDate;
                });

                if ($hasBrotherNextDay) {
                    // Si un frère est trouvé, on arrête la duplication et on passe au statut final
                    $this->setEventTask($newEvent, 'done');
                    $this->em->persist($newEvent);
                    $this->em->flush();
                    break;
                } else {
                    // Sinon, on continue avec le statut "pending" ou "done" pour le dernier jour
                    if ($i === $numberOfPendingDays) {
                        $this->setEventTask($newEvent, 'done');
                    } else {
                        $this->setEventTask($newEvent, 'pending');
                    }

                    // Ajoute et persiste le nouvel événement
                    $eventParent->addEvent($newEvent);
                    $this->em->persist($newEvent);
                    $this->em->flush();
                }

                // Passe au prochain événement
                $event = $newEvent;
                $i++;
            }
        }


    }


    public function duplicateUnrealisedRecurringEvent($event, $RangeStart = "-30days", $RangeEnd = "-1day")
    {
        $eventParent = $event->getEventRecurring();
        $eventsBrothers = $eventParent->getEvents();

        $dateRangeStart = new DateTimeImmutable($RangeStart);
        $dateRangeEnd = new DateTimeImmutable($RangeEnd);

        // Filtrer les événements frères dans la plage en utilisant `dueDate`, et sélectionner ceux avec le statut "unrealised"
        $eventsBrothersInRange = $eventsBrothers->filter(function ($eventBrother) use ($dateRangeStart, $dateRangeEnd) {
            return $eventBrother->getTask()->getTaskStatus() === "unrealised"
                && $eventBrother->getDueDate() >= $dateRangeStart
                && $eventBrother->getDueDate() <= $dateRangeEnd;
        })->toArray();

        // Trier les événements frères par `dueDate` pour faciliter le parcours
        usort($eventsBrothersInRange, function ($a, $b) {
            return $a->getDueDate() <=> $b->getDueDate();
        });

        // Parcourt chaque événement frère dans la plage et vérifie s'il a un frère le jour suivant
        foreach ($eventsBrothersInRange as $eventBrother) {
            $currentDueDate = $eventBrother->getDueDate();
            $nextDueDate = $currentDueDate->modify('+1 day');

            // Vérifie si un événement frère existe le jour suivant
            $hasBrotherNextDay = $eventsBrothers->exists(function ($key, $eventBrotherNextDay) use ($nextDueDate) {
                return $eventBrotherNextDay->getDueDate() == $nextDueDate;
            });

            // Vérifier si le jour précédent est marqué comme `done`
            $previousDay = $currentDueDate->modify('-1 day');
            $hasDonePreviousDay = $eventsBrothers->exists(function ($key, $eventBrotherPreviousDay) use ($previousDay) {
                return $eventBrotherPreviousDay->getDueDate() == $previousDay &&
                    $eventBrotherPreviousDay->getTaskStatus() === 'done';
            });

            // Si le jour précédent est `done`, ne crée pas de nouvel événement pour cette date et continue
            if ($hasDonePreviousDay) {
                continue;
            }

            // Tant qu'il n'y a pas de frère le jour suivant, on crée un nouvel événement pour combler le gap
            while (!$hasBrotherNextDay) {
                // Crée un nouvel événement en dupliquant les propriétés de l'événement actuel
                $newEvent = $this->duplicateEvent($eventBrother);

                // Déterminer un statut pour le nouvel événement et l'ajouter au parent
                $taskStatus = $this->faker->randomElement(['unrealised', 'done']);
                $this->setEventTask($newEvent, $taskStatus);
                $eventParent->addEvent($newEvent);
                $this->em->persist($newEvent);
                $this->em->flush(); // Flush pour persister chaque nouvel événement créé

                // Met à jour les variables pour la boucle suivante
                $nextDueDate = $nextDueDate->modify('+1 day');
                $hasBrotherNextDay = $eventsBrothers->exists(function ($key, $eventBrotherNextDay) use ($nextDueDate) {
                    return $eventBrotherNextDay->getDueDate() == $nextDueDate;
                });

                // Arrêter la duplication si l'événement nouvellement créé est marqué comme `done`
                if ($taskStatus === 'done') {
                    break;
                }
            }
        }
    }



    public function setEventBase(): Event
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


    public function setEventType(Event $event): void
    {
        $createdAt = $event->getCreatedAt();
        $updatedAt = $event->getUpdatedAt();
        $now = new DateTimeImmutable('now');

        $randomType = $this->faker->boolean;

        // Traitement du type Task
        if ($randomType) {
            // Obtention de la dueDate et calcul de la différence
            $dueDate = $event->getDueDate();
            $dueDateDiff = $dueDate->diff($now)->format('%r%a');

            // Cas des événements non récurrents, dans la plage active (-30 jours à aujourd'hui non inclus)
            if (!$event->isRecurring() && $dueDateDiff >= -30 && $dueDateDiff < 0) {
                $taskStatut = $this->faker->randomElement(['done', 'unrealised']);

                // Si l'événement a un statut "unrealised"
                if ($taskStatut === 'unrealised') {
                    do {
                        $newEvent = $this->duplicateEvent($event);

                        // Vérifie si la date d'échéance du nouvel événement est hier et définit le statut sur "done" si c'est le cas
                        if ($newEvent->getDueDate() === $now->modify('-1 day')) {
                            $this->setEventTask($newEvent, 'done');
                            $taskStatus = 'late';  // Met à jour taskStatus pour arrêter la boucle
                        } else {
                            $taskStatus = $this->faker->randomElement(['unrealised', 'done']);
                            $this->setEventTask($newEvent, $taskStatus);
                        }
                        $this->em->persist($newEvent);
                        // Met à jour l'événement courant pour la prochaine itération
                        $event = $newEvent;

                    } while ($taskStatus === 'unrealised');
                } else {
                    $this->setEventTask($event, 'done');
                }
                ;

                // Cas des événements récurrents dans la plage active (-30 jours à aujourd'hui non inclus)
            } elseif ($event->isRecurring() && $dueDateDiff >= -30 && $dueDateDiff < 0) {
                $taskStatut = $this->faker->randomElement(['done', 'unrealised']);
                if ($taskStatut === 'unrealised') {
                    $this->em->persist($event);
                    $event->getEventRecurring()->addEvent($event);
                    $this->em->flush();
                    $this->duplicateUnrealisedRecurringEvent($event);

                } else {
                    $this->setEventTask($event, 'done');
                    $this->em->persist($event);
                    $event->getEventRecurring()->addEvent($event);
                    $this->em->flush();
                }

                // Cas où la dueDate est aujourd'hui
            } elseif ($dueDateDiff === 0) {
                $this->processEvent($event, $now);


                // Cas des événements futurs (dueDate dans le futur)
            } elseif ($dueDateDiff > 0) {
                $taskStatut = $this->faker->randomElement(
                    $event->isRecurring() ?
                    ['todo_modified', 'done', 'pending', 'warning'] :
                    ['todo_modified', 'done', 'pending']
                );

                $this->setEventTask($event, $taskStatut);
                if ($taskStatut === 'pending') {
                    if ($event->isRecurring()) {
                        $this->duplicatePendingRecurringEvent($event);
                    } else {
                        $this->duplicatePendingEvent($event);
                    }
                }

                // Traitement du type Info
            } else {
                $users = $this->retrieveEntities("user", $this);
                $usersCount = count($users);
                $randomNumOfUsers = $this->faker->numberBetween(1, $usersCount);
                $randomUsers = $this->faker->randomElements($users, $randomNumOfUsers);

                $info = new EventInfo();
                $inforeadCounter = 0;

                foreach ($randomUsers as $user) {
                    $eventSharedInfo = new EventSharedInfo();
                    $isRead = $this->faker->boolean;

                    if (!$isRead) {
                        $info->setFullyRead(false);
                    } else {
                        $inforeadCounter++;
                    }

                    $eventSharedInfo
                        ->setUser($user)
                        ->setIsRead($isRead)
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt($updatedAt);

                    $this->em->persist($eventSharedInfo);
                    $info->addSharedWith($user);
                }

                $info
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt)
                    ->setUserReadInfoCount($inforeadCounter)
                    ->setSharedWithCount($randomNumOfUsers);

                $this->em->persist($info);
            }
        }
    }



    public function setEventTask($event, $taskStatus)
    {
        $newTask = new EventTask();
        $newTask
            ->setTaskStatus($taskStatus)
            ->setCreatedAt($event->getCreatedAt())
            ->setUpdatedAt($event->getUpdatedAt());

        $event->setTask($newTask);
    }

    // Fonction pour définir le statut et gérer la duplication en fonction du type d'événement
    private function handleEventStatus(Event $event, array $taskStatuses, bool $isRecurring): void
    {
        $taskStatut = $this->faker->randomElement($taskStatuses);
        $this->setEventTask($event, $taskStatut);

        if ($taskStatut === 'pending') {
            if ($isRecurring) {
                $this->duplicatePendingRecurringEvent($event);
            } else {
                $this->duplicatePendingEvent($event);
            }
        }
    }

    public function processEvent(Event $event, DateTimeImmutable $now)
    {
        $dueDateDiff = $event->getDueDate()->diff($now)->days;
        $isRecurring = $event->isRecurring();

        // Statuts en fonction du contexte
        $taskStatusesToday = ['todo', 'todo_modified', 'done', 'pending', 'warning'];
        $taskStatusesAfterToday = ['todo_modified', 'done', 'pending'];

        // Traitement dans le cas today
        if ($dueDateDiff === 0) {
            $this->handleEventStatus($event, $isRecurring ? $taskStatusesToday : array_slice($taskStatusesToday, 0, 4), $isRecurring);

            // Traitement dans le cas après today
        } elseif ($dueDateDiff > 0) {
            $this->handleEventStatus($event, $isRecurring ? array_merge($taskStatusesAfterToday, ['warning']) : $taskStatusesAfterToday, $isRecurring);
        }
    }



    /**
     * Calculate the number of everyday child events.
     */
    private function calculateEverydayChildren(DateTimeImmutable $firstDueDate, ?DateTimeImmutable $lastDueDate, DateTimeImmutable $latestCreationDate, ?DateTimeImmutable $endDate): int
    {
        if ($lastDueDate > $latestCreationDate || $lastDueDate === null) {
            return (int) $firstDueDate->diff($latestCreationDate)->format('%r%a') + 1;
        } else {
            return (int) $firstDueDate->diff($endDate)->format('%r%a') + 1;
        }
    }

    /**
     * Create child events for everyday cases.
     */
    private function createChildEvents($eventRecurring, DateTimeImmutable $firstDueDate, int $numberOfEventsChildren, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        for ($i = 0; $i < $numberOfEventsChildren; $i++) {
            $dueDate = $firstDueDate->modify("+{$i} days");
            [$createdAt, $updatedAt] = $this->calculateCreatedUpdatedDates($dueDate, $createdAtParent);
            [$dateStatus, $activeDay] = $this->calculateDateStatus($dueDate, $now);

            $this->persistEvent($eventRecurring, $dueDate, $createdAt, $updatedAt, $dateStatus, $activeDay);
        }
    }

    /**
     * Handle a period date and create the corresponding child event.
     */
    private function handlePeriodDate($eventRecurring, DateTimeImmutable $dueDate, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        $dueDateInt = (int) $dueDate->diff($now)->format('%r%a');

        // Check active range validity
        if ($dueDateInt > 7 || $dueDateInt < -30) {
            return; // Exclude dates outside of active range
        }

        [$createdAt, $updatedAt] = $this->calculateCreatedUpdatedDates($dueDate, $createdAtParent);
        [$dateStatus, $activeDay] = $this->calculateDateStatus($dueDate, $now);

        $this->persistEvent($eventRecurring, $dueDate, $createdAt, $updatedAt, $dateStatus, $activeDay);
    }

    /**
     * Handle a month day and create the corresponding child event.
     */
    private function handleMonthDay($eventRecurring, DateTimeImmutable $date, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        $isActiveDay = (int) $date->diff($now)->format('%r%a');
        [$createdAt, $updatedAt] = $this->calculateCreatedUpdatedDates($date, $createdAtParent);
        [$dateStatus, $activeDay] = $this->calculateDateStatus($date, $now);

        $this->persistEvent($eventRecurring, $date, $createdAt, $updatedAt, $dateStatus, $activeDay);
    }

    /**
     * Handle a week day and create the corresponding child event.
     */
    private function handleWeekDay($eventRecurring, DateTimeImmutable $date, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        $isActiveDay = (int) $date->diff($now)->format('%r%a');
        [$createdAt, $updatedAt] = $this->calculateCreatedUpdatedDates($date, $createdAtParent);
        [$dateStatus, $activeDay] = $this->calculateDateStatus($date, $now);

        $this->persistEvent($eventRecurring, $date, $createdAt, $updatedAt, $dateStatus, $activeDay);
    }

    /**
     * Calculate createdAt and updatedAt dates based on due date and parent's createdAt.
     */
    private function calculateCreatedUpdatedDates(DateTimeImmutable $dueDate, DateTimeImmutable $createdAtParent): array
    {
        $daysUntilDueDate = (int) $createdAtParent->diff($dueDate)->format('%r%a');
        if ($daysUntilDueDate <= 7) {
            return [$createdAtParent, $createdAtParent]; // Case 1
        } else {
            $adjustedDate = $dueDate->modify('-7 days');
            return [$adjustedDate, $adjustedDate]; // Case 2
        }
    }

    /**
     * Calculate the status of the date based on current time.
     */
    private function calculateDateStatus(DateTimeImmutable $date, DateTimeImmutable $now): array
    {
        $isActiveDayInt = (int) $date->diff($now)->format('%r%a');
        $dateStatus = ($isActiveDayInt >= -3) ? "activeDayRange" : "past";
        $activeDay = ($isActiveDayInt >= -3) ? $isActiveDayInt : null;

        return [$dateStatus, $activeDay];
    }

    /**
     * Persist an event to the database/ without the event having Type and taskStatus set 
     */
    private function persistEvent($eventRecurring, DateTimeImmutable $dueDate, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt, string $dateStatus, ?int $activeDay): void
    {
        $event = $this->setEventBase();
        $event
            ->setIsRecurring(true)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
            ->setDateStatus($dateStatus)
            ->setActiveDay($activeDay)
            ->setDueDate($dueDate);
        $this->em->persist($event);
        $this->setEventType($event);
        $this->em->persist($event);

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
