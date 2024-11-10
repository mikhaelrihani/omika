<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\AppFixtures\BaseFixtures;
use App\DataFixtures\Provider\AppProvider;
use App\Entity\Event\Event;
use App\Entity\Event\EventTask;
use App\Entity\Event\EventInfo;
use App\Entity\Event\EventRecurring;
use App\Entity\Event\EventSharedInfo;
use App\Entity\Event\MonthDay;
use App\Entity\Event\PeriodDate;
use App\Entity\Event\Section;
use App\Entity\Event\WeekDay;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

//! The database will be captured at the time of loading the fixtures. This capture will then be updated by a cron job.
//! However, the further the loading of fixtures is from the current date, the less relevant the active day range data will be.
//! It is recommended to regularly reload the fixtures or create a cron job to reload them, for example every three days.
class EventFixtures extends BaseFixtures implements DependentFixtureInterface
{



    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));
        // Créer les événements
        $this->createEvents(20);
        // Créer les événements samples for tags
        # $this->createSampleEvents(20, 0);
        // Créer les événements récurrence parents
        $this->createEventRecurringParent(5);
        // Créer les événements enfants pour chaque événement récurrence parent
        $this->createEventRecurringChildrens();
    }


    //! Event non recurring
    /**
     * Crée un certain nombre d'événements dont le statut est "activeDayRange".
     *
     * Cette méthode génère des événements en utilisant des timestamps aléatoires
     * pour les dates de création et de mise à jour. Les événements créés auront
     * une date d'échéance située entre la date de création et la date de mise à jour.
     * Le statut de l'événement est défini en fonction de l'intervalle de jours
     * par rapport à la date actuelle.
     *
     * @param int $numEvents Le nombre d'événements à créer.
     *
     * @return void
     */
    public function createEvents($numEvents): void
    {
        for ($e = 0; $e < $numEvents; $e++) {

            $event = $this->setEventBase();
            $event = $this->setTimestamps($event);
            $this->setRelations($event);
        }
    }
    /**
     * Crée une instance d'Event avec des propriétés de base.
     *
     * Cette méthode initialise un nouvel événement avec des valeurs aléatoires pour la description,
     * l'importance et le côté (kitchen ou office). Elle assigne également une section aléatoire
     * à l'événement à partir de celles disponibles dans la base de données.
     *
     * @return Event L'objet Event nouvellement créé avec des propriétés de base.
     */
    public function setEventBase(): Event
    {
        $users = $this->retrieveEntities("user", $this);
        $createdByUser = $this->faker->randomElement($users);
        $updatedByUser = $this->faker->randomElement($users);
        $createdBy = $createdByUser->getFullName();
        $updatedBy = $updatedByUser->getFullName();

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

    public function setTimestamps(Event $event): Event
    {
        $timestamps = $this->faker->createTimeStamps('-15 days', 'now');
        $createdAt = $timestamps[ 'createdAt' ];
        $updatedAt = $timestamps[ 'updatedAt' ];

        // Générer une date d'échéance aléatoire entre la date de création et la date de mise à jour
        $randomDay = rand(1, 30);
        $dueDate = $this->faker->dateTimeImmutableBetween(
            $updatedAt->format('Y-m-d H:i:s'),
            $updatedAt->modify("+{$randomDay} days")->format('Y-m-d H:i:s')
        );
        $dueDate = DateTimeImmutable::createFromFormat('Y-m-d', $dueDate->format('Y-m-d'));

        // Calculer la différence entre la date d'échéance et la date actuelle pour déterminer activeDay et dateStatus
        $now = DateTimeImmutable::createFromFormat('Y-m-d', (new DateTimeImmutable('now'))->format('Y-m-d'));
        $diff = (int) $now->diff($dueDate)->format('%r%a');
        $activeDay = ($diff >= -3 && $diff <= 7) ? $diff : null;

        if ($diff >= -3 && $diff <= 7) {
            $dateStatus = "activeDayRange";
        } elseif ($diff >= -30 && $diff < -3) {
            $dateStatus = "past";
        } else {
            $dateStatus = "future";
        }
        $event
            ->setIsRecurring(False)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
            ->setActiveDay($activeDay)
            ->setDueDate($dueDate)
            ->setDateStatus($dateStatus);

        return $event;
    }
    /**
     * set the event type property for the given event depending on the type.
     *
     * @param Event $event The event entity to set the type for.
     *
     * @return void
     */
    public function setRelations(Event $event, EventRecurring $eventRecurring = null): void
    {
        // Variables de base
        $createdAt = $event->getCreatedAt();
        $updatedAt = $event->getUpdatedAt();
        $now = DateTimeImmutable::createFromFormat('Y-m-d', (new DateTimeImmutable('now'))->format('Y-m-d'));

        $dueDate = $event->getDueDate();
        $diff = (int) $now->diff($dueDate)->format('%r%a'); // Différence en jours entre aujourd'hui et dueDate
        $type = $event->getType();

        if ($type === "task") {
            $this->handleTaskEvent($event, $eventRecurring, $diff);
        } elseif ($type === "info") {
            $this->handleInfoEvent($event, $eventRecurring, $createdAt, $updatedAt);
        }
    }
    private function handleTaskEvent(Event $event, ?EventRecurring $eventRecurring, int $diff): void
    {
        // Événements dans la plage active (passé)
        if ($diff >= -30 && $diff < 0) {
            $taskStatus = $this->faker->randomElement(['done', 'unrealised']);
            if ($taskStatus === 'unrealised') {
                $this->setEventTask($event, 'unrealised');
                if ($eventRecurring) {
                    $this->duplicateUnrealisedRecurringEvent($event, $eventRecurring);
                } elseif (!$eventRecurring) {
                    $this->duplicateNonRecurringUnrealisedEvent($event);
                }
            } else {
                $this->setEventTask($event, 'done');
            }
            if ($eventRecurring)
                $eventRecurring->addEvent($event);

            // Événements dont la dueDate est aujourd'hui ou dans les 7 prochains jours (actif)
        } elseif ($diff >= 0 && $diff <= 7) {
            $taskStatuses = $eventRecurring ? ['todo', 'todo_modified', 'done', 'pending', 'warning'] : ['todo', 'todo_modified', 'done', 'pending'];
            $this->handleEventStatus($event, $taskStatuses, $eventRecurring);

            // Événements dans le futur
        } elseif ($diff > 7) {
            $taskStatuses = $eventRecurring ? ['todo_modified', 'done', 'pending', 'warning'] : ['todo_modified', 'done', 'pending'];
            $this->handleEventStatus($event, $taskStatuses, $eventRecurring);
        }

        $this->em->persist($event);
        $this->em->flush();
        $id = $event->getId();
        $this->addReference("event_{$id}", $event);

    }
    /**
     * Set the task for the event with the given status.
     *
     * @param Event $event The event entity to set the task for.
     * @param string $taskStatus The status of the task to set.
     *
     * @return void
     */
    public function setEventTask($event, $taskStatus): void
    {
        $newTask = new EventTask();
        $newTask
            ->setTaskStatus($taskStatus)
            ->setCreatedAt($event->getCreatedAt())
            ->setUpdatedAt($event->getUpdatedAt());
        $this->em->persist($newTask);

        $event->setTask($newTask);
    }
    private function handleInfoEvent(Event $event, ?EventRecurring $eventRecurring, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): void
    {
        $users = $this->retrieveEntities("user", fixture: $this);
        $randomUsers = $this->faker->randomElements($users, $this->faker->numberBetween(1, count($users)));

        $info = new EventInfo();
        $info->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
            ->setSharedWithCount(count($randomUsers));
        $this->em->persist($info);

        $inforeadCounter = 0;
        foreach ($randomUsers as $user) {
            $isRead = $this->faker->boolean;
            $info->addSharedWith($user);
            if ($isRead)
                $inforeadCounter++;

            $eventSharedInfo = (new EventSharedInfo())
                ->setUser($user)
                ->setEventInfo($info)
                ->setIsRead($isRead)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);
            $this->em->persist($eventSharedInfo);
            $info->addEventSharedInfo($eventSharedInfo);
        }

        $info->setUserReadInfoCount($inforeadCounter)
            ->setIsFullyRead($inforeadCounter === count($randomUsers));
        $event->setInfo($info);
        $event->setDueDate(DateTimeImmutable::createFromFormat('Y-m-d', $event->getDueDate()->format('Y-m-d')));
        $this->em->persist($event);
        if ($eventRecurring) {
            $eventRecurring->addEvent($event);
        }

        $this->em->flush();
        $id = $event->getId();
        $this->addReference("event_{$id}", $event);


    }
    /**
     * Handle the event status based on the given task statuses and whether it is recurring.
     *
     * @param Event $event The event entity to process.
     * @param array $taskStatuses The possible task statuses to assign.
     * @param bool $isRecurring Indicates if the event is recurring.
     *
     * @return void
     */
    private function handleEventStatus(Event $event, array $taskStatuses, EventRecurring $eventRecurring = null): void
    {
        $taskStatut = $this->faker->randomElement($taskStatuses);
        $this->setEventTask($event, $taskStatut);

        if ($eventRecurring) {
            $eventRecurring->addEvent($event);
        }

    }
    /**
     * Duplique un événement non récurrent tant qu'il est non réalisé (unrealised).
     *
     * Cette fonction crée une copie d'un événement donné en modifiant sa date d'échéance
     * et son statut de tâche, jusqu'à ce que le statut soit autre chose que "unrealised".
     * Chaque copie est persistée en base de données.
     *
     * @param Event $event L'événement source à dupliquer
     *
     * @return void
     */
    private function duplicateNonRecurringUnrealisedEvent(Event $originalEvent): void
    {
        do {
            // Créer le nouvel événement en copiant les propriétés de l'original 
            $duplicatedEvent = $this->duplicateEventBase($originalEvent);
            $event = $this->setTimestampsToDuplicatedEvent($duplicatedEvent, $originalEvent);

            // Définit la nouvelle date d'échéance en utilisant uniquement la date
            $dueDate = DateTimeImmutable::createFromFormat('Y-m-d', $event->getDueDate()->format('Y-m-d'));

            // Détermine le statut de la tâche : "late" si la date d'échéance est hier, sinon aléatoirement "unrealised" ou "done"
            $taskStatus = $dueDate->format('Y-m-d') === (new DateTimeImmutable('yesterday'))->format('Y-m-d')
                ? 'late'
                : $this->faker->randomElement(['unrealised', 'done']);

            // Met à jour le statut de la tâche de l'événement
            $this->setEventTask($event, $taskStatus);


            $this->em->persist($event);
            $this->em->flush();
            $id = $event->getId();
            $this->addReference("event_{$id}", $event);

            // Met à jour l'événement pour la prochaine itération si nécessaire
            $originalEvent = $event;

        } while ($taskStatus === 'unrealised'); // Répète la duplication si le statut est encore "unrealised"
    }
    /**
     * Duplique les propriétés d'un événement existant.
     *
     * Cette méthode crée une nouvelle instance d'Event et copie les propriétés pertinentes
     * de l'événement original, y compris le titre, la description, le type, et d'autres
     * propriétés. Une nouvelle section est également créée pour le nouvel événement.
     *
     * @param Event $originalEvent L'événement original dont les propriétés doivent être dupliquées.
     *
     * @return Event Le nouvel événement contenant les propriétés copiées de l'événement original.
     */
    public function duplicateEventBase(Event $originalEvent): Event
    {

        // Create a new Event instance
        $event = new Event();
        // Copy properties from the original event to the new event
        $event
            ->setIsRecurring($originalEvent->isRecurring())
            ->setSide($originalEvent->getSide())
            ->setType($originalEvent->getType())
            ->setTitle($originalEvent->getTitle())
            ->setDescription($originalEvent->getDescription())
            ->setCreatedBy($originalEvent->getCreatedBy())
            ->setUpdatedBy($originalEvent->getUpdatedBy())
            ->setIsImportant($originalEvent->isImportant())
            ->setSection($originalEvent->getSection());
        return $event;
    }
    /**
     * Duplique un événement en ajustant les dates et le statut.
     *
     * Cette méthode crée un nouvel événement basé sur un événement existant, en ajustant
     * la date d'échéance, les dates de création et de mise à jour. Elle définit également
     * le statut de date de l'événement basé sur la différence entre la date d'échéance
     * et la date actuelle.
     *
     * @param Event $event L'événement à dupliquer.
     *
     * @return Event Le nouvel événement dupliqué avec les propriétés ajustées.
     */
    public function setTimestampsToDuplicatedEvent(Event $event, Event $originalEvent): Event
    {
        // Définir la nouvelle date d'échéance et les dates de création/mise à jour en ajoutant un jour
        $dueDate = $originalEvent->getDueDate()->modify('+1 day');
        $createdAt = $dueDate;
        $updatedAt = $dueDate;

        // Définir le contexte de la date actuelle sans l'heure
        $dueDate = DateTimeImmutable::createFromFormat('Y-m-d', $dueDate->format('Y-m-d'));
        $now = DateTimeImmutable::createFromFormat('Y-m-d', (new DateTimeImmutable('now'))->format('Y-m-d'));

        // Calculer la différence en jours entiers entre la date actuelle et la nouvelle date d'échéance
        $diff = (int) $now->diff($dueDate)->format('%r%a');

        // Déterminer le statut de la date et la valeur d'activeDay en fonction de cette différence
        if ($diff >= -3 && $diff <= 7) {
            $dateStatus = "activeDayRange";
            $activeDay = $diff;
        } elseif ($diff >= -30 && $diff < -3) {
            $dateStatus = "past";
            $activeDay = null; // `activeDay` n'est défini que pour l'intervalle `-3 à 7`
        } else {
            $dateStatus = "future";
            $activeDay = null;
        }

        $event
            ->setDateStatus($dateStatus)
            ->setActiveDay($activeDay)
            ->setDueDate($dueDate)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt);

        return $event;
    }


    //! Event Recurring Parent
    /**
     * Crée des événements récurrents parent.
     *
     * Cette méthode génère des événements récurrents en simulant un environnement de test
     * réaliste, en tenant compte des fenêtres temporelles définies par les statuts
     * `past`, `activeDayRange`, et `future`. Chaque événement récurrent est créé avec
     * des dates de début et de fin de période, et des types de récurrence aléatoires sont
     * attribués.
     *
     * @return void
     */
    public function createEventRecurringParent(int $numEvents): void
    {
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(EventRecurring::class)->count([]) > 0) {
            $numEvents = 2;
        }
        for ($e = 0; $e < $numEvents; $e++) {
            $eventRecurring = $this->setRecurringParentBase();
            $eventRecurring = $this->setRecurringParentsRelations($eventRecurring);
            $this->em->persist($eventRecurring);
            $this->addReference("eventRecurring_{$e}", $eventRecurring);
        }
        $this->em->flush();
    }
    public function setRecurringParentBase(): EventRecurring
    {
        $timestamps = $this->faker->createTimeStamps('-15 days', 'now');
        $createdAt = $timestamps[ 'createdAt' ];
        $updatedAt = $timestamps[ 'updatedAt' ];

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
            $eventRecurring->setEveryday(False);
        }
        return $eventRecurring;
    }
    public function setRecurringParentsRelations(EventRecurring $eventRecurring): EventRecurring
    {
        // Randomly choose one of three recurrence types: month days, week days, specific dates
        $recurrenceType = rand(1, 3);
        $randomIndex = rand(1, 4);

        switch ($recurrenceType) {
            case 1: // Days of the month
                $monthDays = [];
                for ($i = 0; $i < $randomIndex; $i++) {
                    $randDay1 = (int) $eventRecurring->getPeriodeStart()->format('d');
                    $randDay2 = (int) $eventRecurring->getPeriodeEnd()->format('d');
                    ($randDay1 >= $randDay2) ? $randomDay = rand($randDay2, min($randDay1, 30)) :
                        $randomDay = rand($randDay1, min($randDay2, 30));

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
                $baseDate = $eventRecurring->getCreatedAt();// Date minimale autorisée
                $latestDate = $eventRecurring->getPeriodeEnd(); // Date maximale autorisée

                for ($i = 0; $i < $randomIndex; $i++) {
                    do {
                        // Génère un intervalle aléatoire de jours à ajouter
                        $interval = new DateInterval('P' . rand(1, 30) . 'D'); // jusqu'à 30 jours de différence
                        $randomDate = $baseDate->add($interval);
                    } while ($randomDate < $baseDate || $randomDate > $latestDate || in_array($randomDate, $periodDates, false));

                    // Ajouter la date unique à la collection
                    $periodDate = new PeriodDate();
                    $periodDate->setDate($randomDate);
                    $eventRecurring->addPeriodDate($periodDate);
                    $periodDates[] = $randomDate;
                }

                break;
        }

        return $eventRecurring;
    }
    /**
     * Duplique les événements récurrents non réalisés dans une plage de dates spécifiée.
     *
     * Cette méthode parcourt les événements frères d'un événement récurrent donné et duplique ceux qui sont marqués
     * comme non réalisés (status "unrealised") dans une plage de dates définie par les paramètres $RangeStart et 
     * $RangeEnd. Si un événement frère existe le jour suivant, aucun nouvel événement ne sera créé pour combler 
     * l'écart. Si le jour précédent d'un événement est marqué comme fait (status "done"), aucun événement ne sera créé 
     * pour ce jour-là.
     *
     * @param Event $event L'événement dont on souhaite dupliquer les événements récurrents non réalisés.
     * @param string $RangeStart La date de début de la plage de dates (par défaut: "-30days").
     * @param string $RangeEnd La date de fin de la plage de dates (par défaut: "-1day").
     *
     * @return void
     */
    public function duplicateUnrealisedRecurringEvent(Event $originalEvent, EventRecurring $eventRecurring)
    {

        $dateRangeStart = DateTimeImmutable::createFromFormat('Y-m-d', (new DateTimeImmutable("-30 days"))->format('Y-m-d'));
        $dateRangeEnd = DateTimeImmutable::createFromFormat('Y-m-d', (new DateTimeImmutable("-1 day"))->format('Y-m-d'));


        // Exclure l'événement actuel des frères
        $eventsBrothers = $eventRecurring->getEvents()->filter(function ($brotherEvent) use ($originalEvent) {
            return $brotherEvent !== $originalEvent;
        });

        // Vérifier si, après exclusion, la liste est vide
        if ($eventsBrothers->isEmpty()) {
            // Si aucun frère n'existe, dupliquer jusqu'à créer un événement 'done'
            $nextDueDate = $originalEvent->getDueDate()->modify('+1 day');

            while (true) {
                $duplicateEvent = $this->duplicateEventBase($originalEvent);
                $event = $this->setTimestampsToDuplicatedEvent($duplicateEvent, $originalEvent);
                $taskStatus = $this->faker->randomElement(['unrealised', 'done']);
                $this->setEventTask($event, $taskStatus);
                $this->em->persist($event);
                $this->em->flush();

                $eventRecurring->addEvent($event);
                $id = $event->getId();
                $this->addReference("event_{$id}", $event);

                if ($taskStatus === 'done') {
                    break;
                }

                // Préparer la prochaine date pour le prochain événement à dupliquer
                $nextDueDate = $nextDueDate->modify('+1 day');
            }
        } else {
            // Si des frères existent, filtrer et gérer selon la plage donnée
            $eventsBrothersInRange = $eventsBrothers->filter(function ($eventBrother) use ($dateRangeStart, $dateRangeEnd) {
                if ($eventBrother->getTask() !== null) {//! penser a trouver la raison de pourquoi j ai une erreur gettaskinfo on null
                    return $eventBrother->getTask()->getTaskStatus() === "unrealised"
                        && $eventBrother->getDueDate() >= $dateRangeStart
                        && $eventBrother->getDueDate() <= $dateRangeEnd;
                }
            })->toArray();

            // Trier les événements dans la plage par `dueDate`
            usort($eventsBrothersInRange, function ($a, $b) {
                return $a->getDueDate() <=> $b->getDueDate();
            });

            // Parcourir les événements frères dans la plage et dupliquer en conséquence
            foreach ($eventsBrothersInRange as $eventBrother) {
                $currentDueDate = $eventBrother->getDueDate();
                $nextDueDate = $currentDueDate->modify('+1 day');

                $hasBrotherNextDay = $eventsBrothers->exists(function ($key, $eventBrotherNextDay) use ($nextDueDate) {
                    return $eventBrotherNextDay->getDueDate() == $nextDueDate;
                });

                $previousDay = $currentDueDate->modify('-1 day');
                $hasDonePreviousDay = $eventsBrothers->exists(function ($key, $eventBrotherPreviousDay) use ($previousDay) {
                    return $eventBrotherPreviousDay->getDueDate() == $previousDay &&
                        $eventBrotherPreviousDay->getTask()->getTaskStatus() === 'done';
                });

                if ($hasDonePreviousDay) {
                    continue;
                }

                while (!$hasBrotherNextDay) {
                    $duplicateEvent = $this->duplicateEventBase($eventBrother);
                    $event = $this->setTimestampsToDuplicatedEvent($duplicateEvent, $originalEvent);
                    $taskStatus = $this->faker->randomElement(['unrealised', 'done']);
                    $this->setEventTask($event, $taskStatus);
                    $this->em->persist($event);
                    $this->em->flush();
                    $eventRecurring->addEvent($event);
                    $id = $event->getId();
                    $this->addReference("event_{$id}", $event);

                    $nextDueDate = $nextDueDate->modify('+1 day');
                    $hasBrotherNextDay = $eventsBrothers->exists(function ($key, $eventBrotherNextDay) use ($nextDueDate) {
                        return $eventBrotherNextDay->getDueDate() == $nextDueDate;
                    });

                    if ($taskStatus === 'done') {
                        break;
                    }
                }
            }
        }
    }



    //! Event Recurring Childrens
    public function createEventRecurringChildrens(): void
    {
        $eventsRecurring = $this->retrieveEntities("eventRecurring", $this);

        foreach ($eventsRecurring as $eventRecurring) {

            $data = $this->getTimestampsDataForRecurringChildrens($eventRecurring);

            // Vérifier si la période de l'événement parent chevauche la période active
            if ($data[ "endDate" ] < $data[ "earliestCreationDate" ] || $data[ "startDate" ] > $data[ "latestCreationDate" ]) {
                continue; // Aucun événement enfant à créer pour ce parent
            }

            // Handle everyday events
            if ($data[ "everyday" ]) {
                $this->handleEveryday($data, $eventRecurring);
            }

            // Handle period dates
            if ($data[ "periodDates" ]) {
                $this->handlePeriodDates($data, $eventRecurring);
            }

            // Handle month days
            if ($data[ "monthDays" ]) {
                $this->handleMonthDays($data, $eventRecurring);
            }

            // Handle week days
            if ($data[ "weekDays" ]) {
                $this->handleWeekdays($data, $eventRecurring);
            }


        }
    }
    public function getTimestampsDataForRecurringChildrens(EventRecurring $eventRecurring): array
    {
        $periodDates = $eventRecurring->getPeriodDates();
        $monthDays = $eventRecurring->getMonthDays();
        $weekDays = $eventRecurring->getWeekDays();
        $everyday = $eventRecurring->isEveryday();

        $now = DateTimeImmutable::createFromFormat('Y-m-d', (new DateTimeImmutable('now'))->format('Y-m-d'));
        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $eventRecurring->getPeriodeStart()->format('Y-m-d'));
        $createdAtParent = DateTimeImmutable::createFromFormat('Y-m-d', $eventRecurring->getCreatedAt()->format('Y-m-d'));
        // les events enfants peuvent etre en bdd que entre -30 et +7
        // la periode entre start et end du parent doit etre entre -30 et +7
        $latestCreationDate = $now->modify('+7 days');
        $earliestCreationDate = max($now->modify('-30 days'), $createdAtParent); // On prend la date la plus récente entre -30 jours et la date de création

        $endDate = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            ($eventRecurring->getPeriodeEnd() ?? $latestCreationDate)->format('Y-m-d')
        );// si l'event est illimité,car dans ce cas la period end date est null.

        $data = [
            "earliestCreationDate" => $earliestCreationDate,
            "latestCreationDate"   => $latestCreationDate,
            "startDate"            => $startDate,
            "endDate"              => $endDate,
            "everyday"             => $everyday,
            "periodDates"          => $periodDates,
            "monthDays"            => $monthDays,
            "weekDays"             => $weekDays,
            "createdAtParent"      => $createdAtParent,
            "now"                  => $now,
        ];

        return $data;
    }
    private function setRecurringChildrensTimestamps(Event $event, DateTimeImmutable $dueDate, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        [$createdAt, $updatedAt] = $this->calculateCreatedUpdatedDates($dueDate, $createdAtParent, $now);
        [$dateStatus, $activeDay] = $this->calculateDateStatus($dueDate, $now);

        $event
            ->setIsRecurring(true)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
            ->setDateStatus($dateStatus)
            ->setActiveDay($activeDay)
            ->setDueDate($dueDate);
    }
    public function handleEveryday(array $data, EventRecurring $eventRecurring): void
    {
        // Ajuster les dates de début et de fin possibles pour les enfants en fonction de la période active
        $firstDueDate = ($data[ "startDate" ] > $data[ "earliestCreationDate" ]) ? $data[ "startDate" ] : $data[ "earliestCreationDate" ];
        $lastDueDate = ($data[ "endDate" ] < $data[ "latestCreationDate" ]) ? $data[ "endDate" ] : $data[ "latestCreationDate" ];
        $numberOfEventsChildren = (int) $firstDueDate->diff($lastDueDate)->format('%r%a') + 1;
        $this->createEverydayChildren($eventRecurring, $firstDueDate, $numberOfEventsChildren, $data[ "createdAtParent" ], $data[ "now" ]);
    }
    public function handlePeriodDates(array $data, EventRecurring $eventRecurring): void
    {
        foreach ($data[ "periodDates" ] as $periodDate) {
            $dueDate = $periodDate->getDate();
            $diff = (int) $data[ "now" ]->diff($dueDate)->format('%r%a');
            // Check active range validity
            if ($diff > 7 || $diff < -30) {
                continue; // Exclude dates outside of active range
            } else {
                $this->createChildren($eventRecurring, $dueDate, $data[ "createdAtParent" ], $data[ "now" ]);

            }
        }
    }
    public function handleMonthDays(array $data, EventRecurring $eventRecurring): void
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

                    $this->createChildren($eventRecurring, $dueDate, $data[ "createdAtParent" ], $data[ "now" ]);
                }
            }

            // Passer au mois suivant
            $currentMonthDate = $currentMonthDate->modify('first day of next month');
        }
    }
    public function handleWeekdays(array $data, EventRecurring $eventRecurring): void
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
                    $this->createChildren($eventRecurring, $dueDate, $data[ "createdAtParent" ], $data[ "now" ]);
                }
            }

            // Passer à la semaine suivante
            $currentWeekDate = $currentWeekDate->modify('next week');
        }
    }
    private function createEverydayChildren(EventRecurring $eventRecurring, DateTimeImmutable $firstDueDate, int $numberOfEventsChildren, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {

        $originalEvent = $this->setEventBase();
        for ($i = 0; $i < $numberOfEventsChildren; $i++) {
            ($i === 0) ? $event = $originalEvent : $event = $this->duplicateEventBase($originalEvent);

            $dueDate = $firstDueDate->modify("+{$i} days");

            $this->setRecurringChildrensTimestamps($event, $dueDate, $createdAtParent, $now);
            $this->setRelations($event, $eventRecurring);
        }
    }
    private function createChildren(EventRecurring $eventRecurring, DateTimeImmutable $date, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        $event = $this->setEventBase();
        $this->setRecurringChildrensTimestamps($event, $date, $createdAtParent, $now);
        $this->setRelations($event, $eventRecurring);
    }


    /**
     * Calculate createdAt and updatedAt dates based on due date and parent's createdAt.
     *
     * @param DateTimeImmutable $dueDate The due date for the child event.
     * @param DateTimeImmutable $createdAtParent The creation date of the parent event.
     *
     * @return array An array containing the calculated [createdAt, updatedAt] dates.
     */
    private function calculateCreatedUpdatedDates(DateTimeImmutable $dueDate, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): array
    {
        // pour calculer la date de crátion on doit comprendre que un event est créé des lors qu'il rentre dans la periode active.
        // donc soit il y est déjà a la creation du parent, soit il arrive au jour 7.
        // Lorsque le parent est crée ,createdParent= $now.
        // si le parent est crée dans la periode active, alors la date de creation est la date de creation du parent.
        // sinon la date de creation est egal a $now + (dueDate - now + 7 days)
        if ($createdAtParent->modify("+7 days") < $dueDate) {
            return [$createdAtParent, $createdAtParent]; // Case 1
        } else {
            $creationDate = $now->add($now->diff($dueDate))->modify('-7 days');
            return [$creationDate, $creationDate]; // Case 2
        }
    }

    private function calculateDateStatus(DateTimeImmutable $dueDate, DateTimeImmutable $now): array
    {

        // ici on doit comprendre que le cronjob met a jour les events chaque jour, donc on ne peut pas se baser sur la date de creation pour determiner le statut de la date.
        $activeDayInt = (int) $now->diff($dueDate)->format('%r%a');
        $dateStatus = ($activeDayInt >= -3) ? "activeDayRange" : "past";
        $activeDay = ($activeDayInt >= -3) ? $activeDayInt : null;
        return [$dateStatus, $activeDay];
    }




    //!  Event Sample for Tag
    /**
     * Crée un nombre d'événements d'échantillon en définissant une date d'échéance en fonction du jour passé en paramètre.
     * Crée des événements d'échantillon pour tester et initialiser des données associées aux tags.
     * @param int $numEvents Le nombre d'événements à créer.
     * @param int $day       Le nombre de jours ajoutés à la date actuelle pour définir la `dueDate` de chaque événement.
     */
    public function createSampleEvents(int $numEvents, int $day): void
    {
        $data = $this->getSampleEventBaseData();
        for ($e = 0; $e < $numEvents; $e++) {
            $event = new Event();
            $this->setSampleEventBase($event, $data);
            $this->setSampleEventBaseTimestamps($day, $event);
            $this->setRelations($event);
        }
    }
    /**
     * Récupère et retourne les données de base pour les événements d'échantillon.
     *
     * @return array Données de base pour un événement, telles que section, titre, type et côté.
     */
    public function getSampleEventBaseData(): array
    {
        $users = $this->retrieveEntities("user", $this);
        $sections = $this->retrieveEntities("section", $this);
        return [
            "description" => $this->faker->sentence,
            "title"       => $this->faker->sentence,
            "isImportant" => $this->faker->boolean,
            "side"        => $this->faker->randomElement(['kitchen', 'office']),
            "type"        => $this->faker->randomElement(['task', 'info']),
            "createdBy"   => $this->faker->randomElement($users)->getFullName(),
            "updatedBy"   => $this->faker->randomElement($users)->getFullName(),
            "section"     => $sections[array_rand($sections)],
        ];
    }
    /**
     * Initialise les attributs principaux d'un événement à partir des données passées.
     *
     * @param Event $event L'instance de l'événement à initialiser.
     * @param array $data  Données de base pour l'événement, incluant description, importance, côté, etc.
     *
     * @return Event Retourne l'événement mis à jour.
     */
    public function setSampleEventBase(Event $event, array $data): Event
    {
        return $event
            ->setDescription($data[ "description" ])
            ->setIsImportant($data[ "isImportant" ])
            ->setSide($data[ "side" ])
            ->setTitle($data[ "title" ])
            ->setCreatedBy($data[ "createdBy" ])
            ->setUpdatedBy($data[ "updatedBy" ])
            ->setType($data[ "type" ])
            ->setSection($data[ "section" ]);
    }
    /**
     * Définit les horodatages et les attributs temporels pour un événement.
     *
     * @param int   $day   Nombre de jours après la date actuelle pour définir `dueDate`.
     * @param Event $event L'instance de l'événement pour laquelle les timestamps sont configurés.
     *
     * @return Event Retourne l'événement avec les timestamps définis.
     */
    public function setSampleEventBaseTimestamps(int $day, Event $event): Event
    {
        $timestamps = $this->faker->createTimeStamps('-15 days', 'now');
        $createdAt = $timestamps[ 'createdAt' ];
        $updatedAt = $timestamps[ 'updatedAt' ];
        $nowDayOnly = DateTimeImmutable::createFromFormat('Y-m-d', (new DateTimeImmutable('now'))->format('Y-m-d'));
        $dueDate = $nowDayOnly->modify("+{$day} days");

        $activeDayInt = (int) $nowDayOnly->diff($dueDate)->format('%r%a');
        $activeDay = ($activeDayInt >= -3 && $activeDayInt <= 7) ? $activeDayInt : null;

        $dueDateDiff = (int) $nowDayOnly->diff($dueDate)->format('%r%a');
        $dateStatus = $dueDateDiff >= -3 && $dueDateDiff <= 7 ? "activeDayRange"
            : ($dueDateDiff >= -30 && $dueDateDiff < -3 ? "past" : "future");

        return $event
            ->setIsRecurring(false)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
            ->setActiveDay($activeDay)
            ->setDueDate($dueDate)
            ->setDateStatus($dateStatus);
    }



    /**
     * Get the dependencies for this fixture.
     *
     * @return array An array of classes that this fixture depends on.
     */
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

}
