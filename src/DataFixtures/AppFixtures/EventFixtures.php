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


    public function __construct(private EventDuplicationService $eventDuplicationService)
    {

    }

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

    public function setEventType(Event $event): void
    {
        $createdAt = $event->getCreatedAt();
        $updatedAt = $event->getUpdatedAt();
        $activeDay = $event->getActiveDay();

        $type = $this->faker->boolean;
        // traitement du type Task
        if ($type) {
            // traitement dans le cas ou Le dateStatus est "activeDayRange"
            if ($activeDay < 0 && !null) {
                $taskStatut = $this->faker->randomElement(['done', 'unrealised']);
                // si l event est marqué comme unrealised, la logique veut que le lendemain on est le meme event(duplicate) avec un task status = "todo" si nous sommes today, sinon le status reste unrealised.
                if ($taskStatut === 'unrealised') {
                    for ($i = $activeDay; $i < 0; $i++) {
                        if ($i === -1) {
                            $today = new DateTimeImmutable(datetime: 'now');

                            $newEvent = $this->eventDuplicationService->duplicateEventProperties($event);
                            // ajout des timestamps
                            $newEvent
                                ->setActiveDay(0)
                                ->setDateStatus('activeDayRange')
                                ->setDueDate($today)
                                ->setCreatedAt($today)
                                ->setUpdatedAt($today);

                            // ajout des relations
                            $newTask = new EventTask();
                            $newTask
                                ->setTaskStatus('late')
                                ->setCreatedAt($today)
                                ->setUpdatedAt($today);
                            $newEvent->setTask($newTask);

                        } elseif ($activeDay === -2 || $activeDay === -3) {
                            $newEvent = $this->eventDuplicationService->duplicateEventProperties($event);
                            $day = $event->getCreatedAt()->modify("+1 days");
                            // ajout des timestamps
                            $newEvent
                                ->setActiveDay(-1)
                                ->setDateStatus('activeDayRange')
                                ->setDueDate($day)
                                ->setCreatedAt($day)
                                ->setUpdatedAt($day);

                            // ajout des relations
                            $newTask = new EventTask();
                            $newTask
                                ->setTaskStatus('unrealised')
                                ->setCreatedAt($day)
                                ->setUpdatedAt($day);
                            $newEvent->setTask($newTask);
                        }
                    }
                }
            } elseif ($activeDay >= 0 && !null) {
                $task = new EventTask();
                $task
                    ->setTaskStatus($this->faker->randomElement(['todo', `todo_modified`, 'done', 'pending']))
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt);
                $event->setTask($task);

                // traitement dans le cas ou Le dateStatus est "future" 
            } elseif ($event->getDateStatus() === "future") {
                $task = new EventTask();
                $task
                    ->setTaskStatus($this->faker->randomElement([`todo_modified`, 'done', 'pending']))
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt);
                $event->setTask($task);

                // traitement dans le cas ou Le dateStatus est "past"
            } else if ($event->getDateStatus() === "past") {
                $task = new EventTask();
                $task
                    ->setTaskStatus($this->faker->randomElement(['done', 'unrealised']));
                if ($task->getTaskStatus() === 'unrealised') {
                    // cela veut dire qu'au moin un event a été créé le jour suivant.
                    // donc on va faire un random sur le nombre de jour d'affilé ou l'event du lendemain est aussi unrealised.
                    $randomUnrealisedDays = $this->faker->numberBetween(1, 3);
                    for ($i = 0; $i <= $randomUnrealisedDays; $i++) {

                        $newEvent = $this->eventDuplicationService->duplicateEventProperties($event);
                        $dueDate = $event->getDueDate();
                        $createdAt = $event->getCreatedAt();
                        $updatedAt = $event->getUpdatedAt();


                        // ajout des timestamps
                        $newEvent
                            ->setActiveDay(null)
                            ->setDateStatus('past')
                            ->setDueDate($dueDate->modify("+{$i} days"))
                            ->setCreatedAt($createdAt->modify("+{$i} days"))
                            ->setUpdatedAt($updatedAt->modify("+{$i} days"));

                        // ajout des relations
                        $newTask = new EventTask();
                        ($i === $randomUnrealisedDays) ? $taskStatut = "done" : $taskStatut = "unrealised";
                        $newTask
                            ->setTaskStatus($taskStatut)
                            ->setCreatedAt($createdAt->modify("+{$i} days"))
                            ->setUpdatedAt($updatedAt->modify("+{$i} days"));
                        $newEvent->setTask($newTask);

                    }
                } else {
                    $task
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt($updatedAt);
                    $event->setTask($task);
                }

            }

            // traitement du type Info
        } else {

            $users = $this->retrieveEntities("user", $this);
            $usersCount = count($users);
            $randomNumOfUsers = $this->faker->numberBetween(1, $usersCount);
            $randomUsers = [];
            $randomUsers[] = $randomUsers[array_rand($users, $randomNumOfUsers)];

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
                $info->addSharedWith($user);

            }
            $info
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setUserReadInfoCount($inforeadCounter)
                ->setSharedWithCount($randomNumOfUsers);
        }
    }

    public function setEventRecurringChildType($event, $eventParent): void
    {
        // la diff entre seteventType est le fait que un eventUnique ne sera pas duplique dans le tps, un recuuring oui.
        // on va devoir gerer le cas ou un evnt est unrealisede t duplique le lendemain et eviter un doublon si la recurrence avait prévu un event similaire le lendemain.
        // un event peut avoir un status warning
        // il y aun un champs recurring pour l id du parent
        // il faut donc checker sur cchaque eventrecurring les periodes de repetition et creer les events enfants en fonctions de weekly, monthly, everyday, perioddate
        // methode pour traduire un integer(représentant un jour du mois, ou un mois)vers un timestamps pour horodater l'event enfant.
        $createdAt = $event->getCreatedAt();
        $updatedAt = $event->getUpdatedAt();
        $activeDay = $event->getActiveDay();

        //! dateStatus = past && 
        $type = $this->faker->boolean;
        // traitement du type Task 
        if ($type) {
            // traitement dans le cas ou Le dateStatus est "activeDayRange"
            if ($activeDay < 0 && !null) {
                $taskStatut = $this->faker->randomElement(['done', 'unrealised']);
                // si l event est marqué comme unrealised, la logique veut que le lendemain on est le meme event(duplicate) avec un task status = "todo" si nous sommes today, sinon le status reste unrealised.
                if ($taskStatut === 'unrealised') {
                    for ($i = $activeDay; $i < 0; $i++) {
                        if ($i === -1) {
                            $today = new DateTimeImmutable(datetime: 'now');

                            $newEvent = $this->eventDuplicationService->duplicateEventProperties($event);
                            // ajout des timestamps
                            $newEvent
                                ->setActiveDay(0)
                                ->setDateStatus('activeDayRange')
                                ->setDueDate($today)
                                ->setCreatedAt($today)
                                ->setUpdatedAt($today);

                            // ajout des relations
                            $newTask = new EventTask();
                            $newTask
                                ->setTaskStatus('late')
                                ->setCreatedAt($today)
                                ->setUpdatedAt($today);
                            $newEvent->setTask($newTask);

                        } elseif ($activeDay === -2 || $activeDay === -3) {
                            $newEvent = $this->eventDuplicationService->duplicateEventProperties($event);
                            $day = $event->getCreatedAt()->modify("+1 days");
                            // ajout des timestamps
                            $newEvent
                                ->setActiveDay(-1)
                                ->setDateStatus('activeDayRange')
                                ->setDueDate($day)
                                ->setCreatedAt($day)
                                ->setUpdatedAt($day);

                            // ajout des relations
                            $newTask = new EventTask();
                            $newTask
                                ->setTaskStatus('unrealised')
                                ->setCreatedAt($day)
                                ->setUpdatedAt($day);
                            $newEvent->setTask($newTask);
                        }
                    }
                }
            } elseif ($activeDay >= 0 && !null) {
                $task = new EventTask();
                $task
                    ->setTaskStatus($this->faker->randomElement(['todo', `todo_modified`, 'done', 'pending']))
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt);
                $event->setTask($task);

                // traitement dans le cas ou Le dateStatus est "future" 
            } elseif ($event->getDateStatus() === "future") {
                $task = new EventTask();
                $task
                    ->setTaskStatus($this->faker->randomElement([`todo_modified`, 'done', 'pending']))
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt);
                $event->setTask($task);

                // traitement dans le cas ou Le dateStatus est "past"
            } else if ($event->getDateStatus() === "past") {
                $task = new EventTask();
                $task
                    ->setTaskStatus($this->faker->randomElement(['done', 'unrealised']));
                if ($task->getTaskStatus() === 'unrealised') {
                    // cela veut dire qu'au moin un event a été créé le jour suivant.
                    // donc on va faire un random sur le nombre de jour d'affilé ou l'event du lendemain est aussi unrealised.
                    $randomUnrealisedDays = $this->faker->numberBetween(1, 3);
                    for ($i = 0; $i <= $randomUnrealisedDays; $i++) {

                        $newEvent = $this->eventDuplicationService->duplicateEventProperties($event);
                        $dueDate = $event->getDueDate();
                        $createdAt = $event->getCreatedAt();
                        $updatedAt = $event->getUpdatedAt();


                        // ajout des timestamps
                        $newEvent
                            ->setActiveDay(null)
                            ->setDateStatus('past')
                            ->setDueDate($dueDate->modify("+{$i} days"))
                            ->setCreatedAt($createdAt->modify("+{$i} days"))
                            ->setUpdatedAt($updatedAt->modify("+{$i} days"));

                        // ajout des relations
                        $newTask = new EventTask();
                        ($i === $randomUnrealisedDays) ? $taskStatut = "done" : $taskStatut = "unrealised";
                        $newTask
                            ->setTaskStatus($taskStatut)
                            ->setCreatedAt($createdAt->modify("+{$i} days"))
                            ->setUpdatedAt($updatedAt->modify("+{$i} days"));
                        $newEvent->setTask($newTask);

                    }
                } else {
                    $task
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt($updatedAt);
                    $event->setTask($task);
                }

            }

            // traitement du type Info
        } else {

            $users = $this->retrieveEntities("user", $this);
            $usersCount = count($users);
            $randomNumOfUsers = $this->faker->numberBetween(1, $usersCount);
            $randomUsers = [];
            $randomUsers[] = $randomUsers[array_rand($users, $randomNumOfUsers)];

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
                $info->addSharedWith($user);

            }
            $info
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setUserReadInfoCount($inforeadCounter)
                ->setSharedWithCount($randomNumOfUsers);
        }
    }

    public function createEventsChildrenforEachEventRecurringParent(): void
    {
        $eventsRecurring = $this->retrieveEntities("eventRecurring", $this);

        foreach ($eventsRecurring as $eventRecurring) {

            $periodDates = $eventRecurring->getPeriodDates();
            $monthDays = $eventRecurring->getMonthDays();
            $weekDays = $eventRecurring->getWeekDays();
            $everyday = $eventRecurring->isEveryday();


            $startDate = $eventRecurring->getPeriodeStart();
            $endDate = $eventRecurring->getPeriodeEnd();
            // on filtre sur start date pour la creation ou non de l' eventchild en verifiant si levent recurrantparent a une date de periode start superierue a 7 jours from today.
            $startDateIsFuture = (int) $startDate->diff(new DateTimeImmutable('now'))->format('%r%a');
            if ($startDateIsFuture > 7 || $startDateIsFuture < -30) {
                // si oui alors l'event ne sera pas créé/inscrit en bdd
                continue;
                // si non alors on va creer autant d'events enfants que le nombre de jour entre periode start et le septieme jour de activeDayRange.
                // on utilisera ensuite a chaque boucle la methode setEventRecurringChildType pour attribuer un type a chaque event enfant en fonction du type de l event child precedent.

            }


            // case : isEveryday (avec ou sans periodend)
            if ($everyday) {
                // on calcule le nombre d'eventChild 
                $endDateMax = new DateTimeImmutable('+7 days');
                if ($endDate > $endDateMax || $endDate === null) {
                    $numberOfEventsChildren = (int) $startDate->diff($endDateMax->format('%r%a')) + 1;
                } else {
                    $numberOfEventsChildren = (int) $startDate->diff($endDate->format('%r%a')) + 1;
                }
            }

            for ($i = 0; $i < $numberOfEventsChildren; $i++) {
                $today = $startDate->modify("+{$i} days");
                $isActiveDay = (int) $today->diff(new DateTimeImmutable('now'))->format('%r%a');
                if ($isActiveDay >= -3) {
                    $dateStatus = "activeDayRange";
                    $activeDay = $isActiveDay;
                } else {
                    $dateStatus = "past";
                    $activeDay = null;
                }
                $createdAt = $updatedAt = $today;
                $event = $this->getEventBase();
                $event
                    ->setIsRecurring(True)
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt)
                    ->setDateStatus($dateStatus)
                    ->setActiveDay($activeDay)
                    ->setDueDate($today);

                $eventRecurring->addEvent($event);
                $this->em->persist($eventRecurring);
            }
            // case : periodDates
            if ($periodDates) {
                foreach ($periodDates as $periodDate) {
                    $date = $periodDate->getDate();
                    $isActiveDay = (int) $date->diff(new DateTimeImmutable('now'))->format('%r%a');
                    if ($isActiveDay > 7 || $isActiveDay < -30) {
                        //on verifie que l'event est dans la feneter d'inscription en bdd qui est de 30 jours avant la date du jour et 7 jours apres.
                        continue;
                    } elseif ($isActiveDay >= -3) {
                        $dateStatus = "activeDayRange";
                        $activeDay = $isActiveDay;
                    } else {
                        $dateStatus = "past";
                        $activeDay = null;
                    }
                    $createdAt = $updatedAt = $date;
                    $event = $this->getEventBase();
                    $event
                        ->setIsRecurring(True)
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt($updatedAt)
                        ->setDateStatus($dateStatus)
                        ->setActiveDay($activeDay)
                        ->setDueDate($date);

                    $eventRecurring->addEvent($event);
                    $this->em->persist($eventRecurring);
                }
            }
            // case : monthDays
            if ($monthDays) {
                foreach ($monthDays as $monthDay) {
                    $day = $monthDay->getDay();
                    $date = new DateTimeImmutable("now");
                    $date = $date->modify("first day of this month");
                    $date = $date->modify("+{$day} days");
                    $isActiveDay = (int) $date->diff(new DateTimeImmutable('now'))->format('%r%a');
                    if ($isActiveDay >= -3) {
                        $dateStatus = "activeDayRange";
                        $activeDay = $isActiveDay;
                    } else {
                        $dateStatus = "past";
                        $activeDay = null;
                    }
                    $createdAt = $updatedAt = $date;
                    $event = $this->getEventBase();
                    $event
                        ->setIsRecurring(True)
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt($updatedAt)
                        ->setDateStatus($dateStatus)
                        ->setActiveDay($activeDay)
                        ->setDueDate($date);

                    $eventRecurring->addEvent($event);
                    $this->em->persist($eventRecurring);
                }
            }
            // case : weekDays
            if ($weekDays) {
                foreach ($weekDays as $weekDay) {
                    $day = $weekDay->getDay();
                    $date = new DateTimeImmutable("now");
                    $date = $date->modify("this week");
                    $date = $date->modify("+{$day} days");
                    $isActiveDay = (int) $date->diff(new DateTimeImmutable('now'))->format('%r%a');
                    if ($isActiveDay >= -3) {
                        $dateStatus = "activeDayRange";
                        $activeDay = $isActiveDay;
                    } else {
                        $dateStatus = "past";
                        $activeDay = null;
                    }
                    $createdAt = $updatedAt = $date;
                    $event = $this->getEventBase();
                    $event
                        ->setIsRecurring(True)
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt($updatedAt)
                        ->setDateStatus($dateStatus)
                        ->setActiveDay($activeDay)
                        ->setDueDate($date);

                    $eventRecurring->addEvent($event);
                    $this->em->persist($eventRecurring);
                }
            }


        }



    }



    public function createEvents($numEvents): void
    {

        for ($e = 0; $e < $numEvents; $e++) {

            $event = $this->createEvent();
            $this->setEventType($event);
        }
    }

    public function createEvent(): Event
    {
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

        $event = $this->getEventBase();
        $event
            ->setIsRecurring(False)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
            ->setActiveDay($activeDay)
            ->setDueDate($dueDate);

        $this->em->persist($event);
        return $event;
    }




    public function createEventRecurringParent(): void
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

        for ($e = 0; $e < 10; $e++) {
            $timeStamps = $this->faker->createTimeStamps();
            $createdAt = $timeStamps[ 'createdAt' ];
            $updatedAt = $timeStamps[ 'updatedAt' ];

            // Générer les dates de début et de fin de période
            $periodeStart = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), $updatedAt->format('Y-m-d H:i:s'));
            $periodeEnd = $this->faker->dateTimeImmutableBetween($updatedAt->format('Y-m-d H:i:s'), "+1 month");

            // Initialisation de l'EventRecurring
            $eventRecurring = new EventRecurring();
            $eventRecurring
                ->setPeriodeStart($periodeStart)
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
            $this->em->persist($eventRecurring);
            $this->addReference("eventRecurring_{$e}", $eventRecurring);


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
