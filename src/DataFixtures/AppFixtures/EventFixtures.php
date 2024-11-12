<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\Event\Event;
use App\Entity\Event\EventTask;
use App\Entity\Event\EventInfo;
use App\Entity\Event\EventRecurring;
use App\Entity\Event\EventSharedInfo;
use App\Entity\Event\MonthDay;
use App\Entity\Event\PeriodDate;
use App\Entity\Event\Section;
use App\Entity\Event\WeekDay;
use App\Entity\User\User;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;


class EventFixtures extends BaseFixtures implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->createEvents(20);
        $this->createEventRecurringParent(5);
        $this->createEventRecurringChildrens();
        // you can adjust here the type of event sample you want to create
        $type = $this->faker->randomElement(['task', 'info']);
        $this->createSampleEvents(5, 0, $type);
    }


    //! Event non recurring -----------------------------------------------------------------------------------


    /**
     * Génère et configure un nombre donné d'événements avec leurs relations et timestamps.
     *
     * @param int $numEvents Nombre d'événements à créer.
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
     * Initialise un nouvel événement avec des informations de base aléatoires.
     * Récupère les utilisateurs et les sections disponibles pour créer un événement personnalisé.
     *
     * @return Event L'événement configuré avec des valeurs de base.
     */
    public function setEventBase(): Event
    {
        $users = $this->em->getRepository(User::class)->findAll();

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
    /**
     * Définit les timestamps (création, mise à jour, échéance) pour un événement.
     * Calcule la date d'échéance aléatoire, le statut de la date et le jour actif.
     *
     * @param Event $event L'événement à mettre à jour avec les timestamps.
     * @return Event L'événement avec les timestamps configurés.
     */
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
     * Définit les relations et le statut des tâches pour un événement en fonction de son type
     * et de son échéance. Utilise `EventRecurring` si l'événement est récurrent.
     *
     * @param Event $event L'événement à configurer.
     * @param EventRecurring|null $eventRecurring (Optionnel) L'entité récurrente liée.
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
    /**
     * Gère la configuration des événements de type "task", y compris le statut en fonction de l'échéance.
     * Si l'événement est dans le passé et non réalisé, il peut être dupliqué.
     *
     * @param Event $event L'événement à traiter.
     * @param EventRecurring|null $eventRecurring L'entité récurrente, si applicable.
     * @param int $diff Différence en jours entre aujourd'hui et la date d'échéance de l'événement.
     */
    private function handleTaskEvent(Event $event, ?EventRecurring $eventRecurring, int $diff): void
    {
        // Événements dans la plage active (passé)
        if ($diff >= -30 && $diff < 0) {
            $taskStatus = $this->faker->randomElement(['done', 'unrealised']);

            if ($taskStatus === 'unrealised') {
                $this->setTaskStatus($event, 'unrealised');
                if (!$eventRecurring)
                    $this->duplicateNonRecurringUnrealisedEvent($event);
            } else {
                $this->setTaskStatus($event, 'done');
            }

            if ($eventRecurring)
                $eventRecurring->addEvent($event);

            // Événements dont la dueDate est aujourd'hui ou dans les 7 prochains jours (actif)
        } elseif ($diff >= 0 && $diff <= 7) {
            $taskStatuses = $eventRecurring ? ['todo', 'todo_modified', 'done', 'pending', 'warning'] : ['todo', 'todo_modified', 'done', 'pending'];
            $this->handleTaskStatus($event, $taskStatuses, $eventRecurring);

            // Événements dans le futur
        } elseif ($diff > 7) {
            $taskStatuses = $eventRecurring ? ['todo_modified', 'done', 'pending', 'warning'] : ['todo_modified', 'done', 'pending'];
            $this->handleTaskStatus($event, $taskStatuses, $eventRecurring);
        }

        $this->em->persist($event);
        $this->em->flush();
        $id = $event->getId();
        $this->addReference("event_{$id}", $event);

    }
    /**
     * Affecte un statut de tâche à l'événement en fonction de la plage des statuts autorisés.
     * Lie l'événement à une entité récurrente si nécessaire.
     *
     * @param Event $event L'événement pour lequel configurer le statut.
     * @param array $taskStatuses Liste des statuts de tâche possibles.
     * @param EventRecurring|null $eventRecurring L'entité récurrente si elle est définie.
     */
    private function handleTaskStatus(Event $event, array $taskStatuses, EventRecurring $eventRecurring = null): void
    {
        $taskStatut = $this->faker->randomElement($taskStatuses);
        $this->setTaskStatus($event, $taskStatut);

        if ($eventRecurring) {
            $eventRecurring->addEvent($event);
        }

    }
    /**
     * Crée un nouvel objet EventTask avec un statut de tâche donné et lie cette tâche à l'événement spécifié.
     *
     * @param Event $event L'événement auquel le statut de la tâche sera appliqué.
     * @param string $taskStatus Statut de tâche à assigner.
     */
    public function setTaskStatus($event, $taskStatus): void
    {
        $newTask = new EventTask();
        $newTask
            ->setTaskStatus($taskStatus)
            ->setCreatedAt($event->getCreatedAt())
            ->setUpdatedAt($event->getUpdatedAt());
        $this->em->persist($newTask);

        $event->setTask($newTask);
    }
    /**
     * Configure les informations de partage pour un événement de type "info", incluant les utilisateurs
     * avec qui il est partagé, et le statut de lecture de chaque utilisateur.
     *
     * @param Event $event L'événement de type "info" pour lequel créer les informations de partage.
     * @param EventRecurring|null $eventRecurring Instance d'événement récurrent si applicable.
     * @param DateTimeImmutable $createdAt Date de création de l'information.
     * @param DateTimeImmutable $updatedAt Date de mise à jour de l'information.
     */
    private function handleInfoEvent(Event $event, ?EventRecurring $eventRecurring, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): void
    {
        $users = $this->em->getRepository(User::class)->findAll();

        $randomUsers = $this->faker->randomElements($users, $this->faker->numberBetween(1, count($users)));

        $info = new EventInfo();
        $info->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
            ->setSharedWithCount(count($randomUsers));
        $this->em->persist($info);

        $inforeadCounter = 0;
        foreach ($randomUsers as $user) {
            $isRead = $this->faker->boolean(20);
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
     * Crée une duplication d'un événement non récurrent qui n'est pas réalisé, en copiant ses propriétés.
     * La duplication continue jusqu'à ce que le statut de la tâche ne soit plus "unrealised".
     *
     * @param Event $originalEvent L'événement original à dupliquer.
     */
    private function duplicateNonRecurringUnrealisedEvent(Event $originalEvent): void
    {
        do {
            // Créer le nouvel événement en copiant les propriétés de l'original 
            $event = $this->duplicateEventBase($originalEvent);
            $this->setTimestampsToDuplicatedEvent($event, $originalEvent);

            // Définit la nouvelle date d'échéance en utilisant uniquement la date
            $dueDate = DateTimeImmutable::createFromFormat('Y-m-d', $event->getDueDate()->format('Y-m-d'));

            // Détermine le statut de la tâche : "late" si la date d'échéance est hier, sinon aléatoirement "unrealised" ou "done"
            $taskStatus = $dueDate->format('Y-m-d') === (new DateTimeImmutable('yesterday'))->format('Y-m-d')
                ? 'late'
                : $this->faker->randomElement(['unrealised', 'done']);

            // Met à jour le statut de la tâche de l'événement
            $this->setTaskStatus($event, $taskStatus);


            $this->em->persist($event);
            $this->em->flush();
            $id = $event->getId();
            $this->addReference("event_{$id}", $event);

            // Met à jour l'événement pour la prochaine itération si nécessaire
            $originalEvent = $event;

        } while ($taskStatus === 'unrealised'); // Répète la duplication si le statut est encore "unrealised"
    }
    /**
     * Crée une copie d'un événement en reprenant toutes ses propriétés de base, y compris le côté, le type,
     * le titre, la description et l'utilisateur associé.
     *
     * @param Event $originalEvent L'événement à partir duquel créer la copie.
     * @return Event Un nouvel événement avec les mêmes propriétés de base que l'événement original.
     */
    public function duplicateEventBase(Event $originalEvent): Event
    {

        // Create a new Event instance
        $event = new Event();
        // Copy properties from the original event to the new event
        $event
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
     * Configure les timestamps pour un événement dupliqué, en augmentant la date d'échéance d'un jour
     * par rapport à l'événement original, et en recalculant le statut de la date et le jour actif.
     *
     * @param Event $event L'événement dupliqué dont les timestamps sont à configurer.
     * @param Event $originalEvent L'événement original pour référence.
     * @return Event L'événement dupliqué avec les nouveaux timestamps.
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


    //! Event Recurring Parent -----------------------------------------------------------------------------------
    /**
     * Crée un nombre spécifié d'événements récurrents parents et les persiste dans la base de données.
     * Limite la création à deux événements si des instances EventRecurring existent déjà pour éviter
     * une duplication excessive lors du chargement de fixtures.
     *
     * @param int $numEvents Le nombre d'événements récurrents parents à créer.
     */
    public function createEventRecurringParent(int $numEvents): void
    {
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(EventRecurring::class)->count([]) > 0) {
            $numEvents = 2;
        }
        for ($e = 0; $e < $numEvents; $e++) {
            $eventRecurring = $this->setRecurringParentBase();
            if (!$eventRecurring->isEveryday()) {
                $eventRecurring = $this->setRecurringParentsRelations($eventRecurring);
            }
            $this->em->persist($eventRecurring);
            $this->addReference("eventRecurring_{$e}", $eventRecurring);
        }
        $this->em->flush();
    }
    /**
     * Initialise un événement récurrent parent (EventRecurring) en lui assignant une période de début,
     * une période de fin, et des horodatages (création et mise à jour). Définit également si l'événement
     * doit se produire chaque jour (daily) ou non.
     *
     * @return EventRecurring Un nouvel objet EventRecurring avec les informations de base définies.
     */
    public function setRecurringParentBase(): EventRecurring
    {
        $timestamps = $this->faker->createTimeStamps('-15 days', 'now');
        $createdAt = $updatedAt = $timestamps[ 'createdAt' ];

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
            $eventRecurring->setEveryday(false);
        }
        return $eventRecurring;
    }
    /**
     * Détermine et assigne des jours de récurrence à un événement récurrent, selon l'un des trois types
     * de récurrence: jours du mois, jours de la semaine, ou dates spécifiques.
     *
     * - Jours du mois : sélectionne jusqu'à 4 jours aléatoires dans le mois pour l'événement.
     * - Jours de la semaine : sélectionne jusqu'à 4 jours de la semaine pour la récurrence.
     * - Dates spécifiques : génère entre 3 et 10 dates uniques dans la période définie pour l'événement.
     *
     * @param EventRecurring $eventRecurring L'événement récurrent auquel les jours sont ajoutés.
     * @return EventRecurring L'événement récurrent mis à jour avec les jours de récurrence.
     */
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


    //! Event Recurring Childrens -----------------------------------------------------------------------------------
    /**
     * Crée des événements enfants pour chaque événement récurrent parent, en fonction de leur type de récurrence :
     * tous les jours, jours de la semaine, jours du mois ou dates spécifiques. Ensuite, elle duplique
     * les événements enfants de type tâche qui n'ont pas été réalisés.
     */
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
            elseif ($data[ "everyday" ]) {
                $this->handleEveryday($data, $eventRecurring);
            }

            // Handle period dates
            elseif ($data[ "periodDates" ]) {
                $this->handlePeriodDates($data, $eventRecurring);
            }

            // Handle month days
            elseif ($data[ "monthDays" ]) {
                $this->handleMonthDays($data, $eventRecurring);
            }

            // Handle week days
            elseif ($data[ "weekDays" ]) {
                $this->handleWeekdays($data, $eventRecurring);
            }


        }
        // après avoir flushé tous les evenements enfants d'un parent récurrent,
        // on s'occupe maintenant de la duplication des evenements enfants qui sont de type task et qui ont un statut unrealised

        $this->duplicateUnrealisedRecurringChildrens($eventRecurring);
    }
    /**
     * Gère la création d'événements enfants quotidiens pour un événement récurrent qui se produit chaque jour
     * dans une période spécifiée.
     *
     * @param array $data Un tableau contenant les dates de début, de fin, les dates de création et autres informations nécessaires.
     * @param EventRecurring $eventRecurring L'événement récurrent parent.
     */
    public function handleEveryday(array $data, EventRecurring $eventRecurring): void
    {
        // Ajuster les dates de début et de fin possibles pour les enfants en fonction de la période active
        $firstDueDate = ($data[ "startDate" ] > $data[ "earliestCreationDate" ]) ? $data[ "startDate" ] : $data[ "earliestCreationDate" ];
        $lastDueDate = ($data[ "endDate" ] < $data[ "latestCreationDate" ]) ? $data[ "endDate" ] : $data[ "latestCreationDate" ];
        $numberOfEventsChildren = (int) $firstDueDate->diff($lastDueDate)->format('%r%a') + 1;
        $this->createEverydayChildren($eventRecurring, $firstDueDate, $numberOfEventsChildren, $data[ "updatedAtParent" ], $data[ "now" ]);
    }
    /**
     * Gère la création d'événements enfants pour un événement récurrent basé sur des dates spécifiques.
     * Crée un événement enfant pour chaque date unique dans la période active.
     *
     * @param array $data Un tableau contenant les dates de période, et autres informations nécessaires.
     * @param EventRecurring $eventRecurring L'événement récurrent parent.
     */
    public function handlePeriodDates(array $data, EventRecurring $eventRecurring): void
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
    /**
     * Gère la création d'événements enfants pour un événement récurrent basé sur des jours spécifiques du mois.
     * Itère sur chaque mois de la période active et crée des événements pour chaque jour du mois spécifié.
     *
     * @param array $data Un tableau contenant les informations de création et les jours du mois.
     * @param EventRecurring $eventRecurring L'événement récurrent parent.
     */
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

                    $this->createChildren($eventRecurring, $dueDate, $data[ "updatedAtParent" ], $data[ "now" ]);
                }
            }

            // Passer au mois suivant
            $currentMonthDate = $currentMonthDate->modify('first day of next month');
        }
    }
    /**
     * Gère la création d'événements enfants pour un événement récurrent basé sur des jours spécifiques de la semaine.
     * Itère sur chaque semaine de la période active et crée des événements enfants pour les jours de la semaine spécifiés.
     *
     * @param array $data Un tableau contenant les dates de création, les jours de la semaine et autres informations.
     * @param EventRecurring $eventRecurring L'événement récurrent parent.
     */
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
                    $this->createChildren($eventRecurring, $dueDate, $data[ "updatedAtParent" ], $data[ "now" ]);
                }
            }

            // Passer à la semaine suivante
            $currentWeekDate = $currentWeekDate->modify('next week');
        }
    }
    /**
     * Crée des événements enfants quotidiens pour un événement récurrent, en définissant la première date d'échéance
     * et en répétant la création pour chaque jour de la période active.
     *
     * @param EventRecurring $eventRecurring L'événement récurrent parent.
     * @param DateTimeImmutable $firstDueDate La première date d'échéance des événements enfants.
     * @param int $numberOfEventsChildren Le nombre total d'événements enfants à créer.
     * @param DateTimeImmutable $updatedAtParent La date de mise à jour de l'événement parent.
     * @param DateTimeImmutable $now La date actuelle.
     */
    private function createEverydayChildren(EventRecurring $eventRecurring, DateTimeImmutable $firstDueDate, int $numberOfEventsChildren, DateTimeImmutable $updatedAtParent, DateTimeImmutable $now): void
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
    /**
     * Crée un événement enfant unique pour un événement récurrent, en définissant les horodatages
     * et en établissant les relations avec l'événement parent.
     *
     * @param EventRecurring $eventRecurring L'événement récurrent parent.
     * @param DateTimeImmutable $date La date de l'événement enfant.
     * @param DateTimeImmutable $createdAtParent La date de création de l'événement parent.
     * @param DateTimeImmutable $now La date actuelle.
     */
    private function createChildren(EventRecurring $eventRecurring, DateTimeImmutable $date, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        $event = $this->setEventBase();
        $this->setRecurringChildrensTimestamps($event, $date, $createdAtParent, $now);
        $this->setRelations($event, $eventRecurring);
    }


    /**
     * Dupplique les événements enfants de type "task" d'un événement récurrent, en créant de nouveaux
     * événements pour chaque tâche non réalisée.
     *
     * @param EventRecurring $eventRecurring L'événement récurrent parent contenant des événements enfants de type "task".
     */
    public function duplicateUnrealisedRecurringChildrens(EventRecurring $eventRecurring)
    {
        $now = DateTimeImmutable::createFromFormat('Y-m-d', (new DateTimeImmutable('now'))->format('Y-m-d'));
        // on récupère les events tasks 
        $childrensTask = [];
        $childrens = $eventRecurring->getEvents();
        foreach ($childrens as $child) {
            if ($child->getTask()) {
                $childrensTask[] = $child;
            }
        }

        if (count($childrensTask) !== 0) {
            $this->createBrothers($eventRecurring, $childrensTask, $now);
        }
    }
    /**
     * Crée des événements enfants (appelés "brothers") pour les événements enfants non réalisés,
     * et génère les nouveaux événements pour les dates successives jusqu'à atteindre une condition de statut.
     *
     * @param EventRecurring $eventRecurring L'événement récurrent parent.
     * @param array $childrensTask Tableau des événements enfants de type "task" associés à l'événement parent.
     * @param DateTimeImmutable $now La date actuelle utilisée comme référence pour la duplication.
     */
    public function createBrothers(EventRecurring $eventRecurring, array $childrensTask, DateTimeImmutable $now): void
    {
        foreach ($childrensTask as $brother) {
            $currentDueDate = $brother->getDueDate();
            $nextDueDate = $currentDueDate->modify('+1 day');
            if ($nextDueDate > $now) {
                break;
            } elseif ($brother->getTask()->getTaskStatus() !== 'unrealised') {
                continue;
            } else {
                $hasBrotherNextDay = empty(array_filter($childrensTask, function ($brotherNextDay) use ($nextDueDate) {
                    return $brotherNextDay->getDueDate() == $nextDueDate;
                }));
                while ($hasBrotherNextDay) {
                    $brother = $this->createOneBrother($now, $nextDueDate, $brother, $eventRecurring);
                    // Préparer la prochaine date pour le prochain événement à dupliquer
                    $nextDueDate = $nextDueDate->modify('+1 day');
                    $taskStatus = $brother->getTask()->getTaskStatus();
                    if ($taskStatus === 'done' || $taskStatus === 'late') {
                        break;
                    }
                    $hasBrotherNextDay = empty(array_filter($childrensTask, function ($brotherNextDay) use ($nextDueDate) {
                        return $brotherNextDay->getDueDate() == $nextDueDate;
                    }));
                }

            }
        }
        $this->em->flush();
    }
    /**
     * Crée un événement enfant dupliqué (ou "brother") pour un événement non réalisé,
     * en copiant les données et ajustant le statut de la tâche.
     *
     * @param DateTimeImmutable $now La date actuelle.
     * @param DateTimeImmutable $nextDueDate La date d'échéance pour le nouvel événement enfant.
     * @param Event $originalEvent L'événement original à dupliquer.
     * @param EventRecurring $eventRecurring L'événement récurrent parent auquel l'événement enfant est associé.
     *
     * @return Event Le nouvel événement dupliqué (brother).
     */
    public function createOneBrother(DateTimeImmutable $now, DateTimeImmutable $nextDueDate, Event $originalEvent, EventRecurring $eventRecurring): Event
    {

        $event = $this->duplicateEventBase($originalEvent);
        $this->setTimestampsToDuplicatedEvent($event, $originalEvent);
        $taskStatus = ($nextDueDate === $now) ? 'late' : $this->faker->randomElement(['unrealised', 'done']);
        $this->setTaskStatus($event, $taskStatus);
        $event->setIsRecurring(True);

        $this->em->persist($event);
        $eventRecurring->addEvent($event);

        $id = $event->getId();
        $this->addReference("event_{$id}", $event);

        return $event;
    }


    /**
     * Récupère et calcule les données temporelles pour les enfants récurrents d'un événement,
     * en déterminant les périodes de création et de fin ainsi que les jours spécifiques.
     *
     * @param EventRecurring $eventRecurring L'événement récurrent parent.
     *
     * @return array Un tableau contenant les informations temporelles telles que les dates de début, de fin, les jours de la semaine, etc.
     */
    public function getTimestampsDataForRecurringChildrens(EventRecurring $eventRecurring): array
    {
        $periodDates = $eventRecurring->getPeriodDates();
        $monthDays = $eventRecurring->getMonthDays();
        $weekDays = $eventRecurring->getWeekDays();
        $everyday = $eventRecurring->isEveryday();

        $now = DateTimeImmutable::createFromFormat('Y-m-d', (new DateTimeImmutable('now'))->format('Y-m-d'));
        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $eventRecurring->getPeriodeStart()->format('Y-m-d'));
        $updatedAtParent = DateTimeImmutable::createFromFormat('Y-m-d', $eventRecurring->getUpdatedAt()->format('Y-m-d'));
        // les events enfants peuvent etre en bdd que entre -30 et +7
        // la periode entre start et end du parent doit etre entre -30 et +7
        $latestCreationDate = $now->modify('+7 days');
        $earliestCreationDate = max($now->modify('-30 days'), $updatedAtParent); // On prend la date la plus récente entre -30 jours et la date de dernière update

        $endDate = $eventRecurring->getPeriodeEnd() && $eventRecurring->getPeriodeEnd() < $latestCreationDate
            ? DateTimeImmutable::createFromFormat('Y-m-d', $eventRecurring->getPeriodeEnd()->format('Y-m-d'))
            : DateTimeImmutable::createFromFormat('Y-m-d', $latestCreationDate->format('Y-m-d'));


        $data = [
            "earliestCreationDate" => $earliestCreationDate,
            "latestCreationDate"   => $latestCreationDate,
            "startDate"            => $startDate,
            "endDate"              => $endDate,
            "everyday"             => $everyday,
            "periodDates"          => $periodDates,
            "monthDays"            => $monthDays,
            "weekDays"             => $weekDays,
            "updatedAtParent"      => $updatedAtParent,
            "now"                  => $now,
        ];

        return $data;
    }
    /**
     * Définit les horodatages pour les événements enfants récurrents, en ajustant la date de création,
     * la date de mise à jour, et le statut de la date.
     *
     * @param Event $event L'événement enfant dont les horodatages sont à définir.
     * @param DateTimeImmutable $dueDate La date d'échéance de l'événement enfant.
     * @param DateTimeImmutable $updatedAtParent La date de mise à jour de l'événement parent.
     * @param DateTimeImmutable $now La date actuelle.
     */
    private function setRecurringChildrensTimestamps(Event $event, DateTimeImmutable $dueDate, DateTimeImmutable $updatedAtParent, DateTimeImmutable $now): void
    {
        $createdAt = $this->calculateCreatedUpdatedDates($dueDate, $updatedAtParent, $now);
        [$dateStatus, $activeDay] = $this->calculateDateStatus($dueDate, $now);

        $event
            ->setIsRecurring(True)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($createdAt)
            ->setDateStatus($dateStatus)
            ->setActiveDay($activeDay)
            ->setDueDate($dueDate);
    }
    /**
     * Calcule la date de création pour un événement enfant, en tenant compte de la date de mise à jour du parent
     * et de la période active pour un événement récurrent quotidien.
     *
     * @param DateTimeImmutable $dueDate La date d'échéance de l'événement enfant.
     * @param DateTimeImmutable $updatedAtParent La date de mise à jour du parent.
     * @param DateTimeImmutable $now La date actuelle.
     *
     * @return DateTimeImmutable La date de création de l'événement enfant.
     */
    private function calculateCreatedUpdatedDates(DateTimeImmutable $dueDate, DateTimeImmutable $updatedAtParent, DateTimeImmutable $now): DateTimeImmutable
    {
        // pour calculer la date de crátion on doit comprendre que un event everyday est créé des lors qu'il rentre dans la periode active.
        // donc soit il y est déjà a la creation du parent, soit il arrive au jour 7.
        // Lorsque le parent est crée ,createdParent= $now.
        // si le parent est crée dans la periode active, alors la date de creation est la date de creation du parent.
        // sinon la date de creation est egal a $now + (dueDate - now + 7 days)
        if ($updatedAtParent->modify("+7 days") >= $dueDate) {
            $creationDate = $updatedAtParent;
        } else {
            $creationDate = $now->add($now->diff($dueDate))->modify('-7 days');
        }
        return $creationDate;
    }
    /**
     * Calcule le statut de la date d'un événement enfant par rapport à la date actuelle,
     * et détermine si le jour est dans la plage active ou passé.
     *
     * @param DateTimeImmutable $dueDate La date d'échéance de l'événement enfant.
     * @param DateTimeImmutable $now La date actuelle.
     *
     * @return array Un tableau contenant le statut de la date ("activeDayRange" ou "past") et le nombre de jours dans la plage active.
     */
    private function calculateDateStatus(DateTimeImmutable $dueDate, DateTimeImmutable $now): array
    {

        // ici on doit comprendre que le cronjob met a jour les events chaque jour, donc on ne peut pas se baser sur la date de creation pour determiner le statut de la date.
        $activeDayInt = (int) $now->diff($dueDate)->format('%r%a');
        $dateStatus = ($activeDayInt >= -3) ? "activeDayRange" : "past";
        $activeDay = ($activeDayInt >= -3) ? $activeDayInt : null;
        return [$dateStatus, $activeDay];
    }




    //!  Event Sample for Tag ----------------------------------------------------------------------------------------

    /**
     * Crée des événements d'exemple avec des propriétés aléatoires pour un type d'événement donné
     
     * et les lie à une journée spécifique .
     *
     * @param int $numEvents Le nombre d'événements à créer.
     * @param int $day Le nombre de jours à ajouter à la date actuelle pour définir la date d'échéance.
     * @param string $type Le type de l'événement (ex. 'meeting', 'task').
     */
    public function createSampleEvents(int $numEvents, int $day, string $type): void
    {
        $data = $this->getSampleEventBaseData($type);
        for ($e = 0; $e < $numEvents; $e++) {
            $event = new Event();
            $this->setSampleEventBase($event, $data);
            $this->setSampleEventBaseTimestamps($day, $event);
            $this->setRelations($event);
        }
    }
    /**
     * Génère et retourne un tableau de données de base pour un événement en fonction de son type.
     * Les données incluent la description, le titre, le créateur, le statut important, et la section de l'événement.
     *
     * @param string $type Le type de l'événement pour lequel générer des données de base.
     * @return array Un tableau de données de base pour l'événement, comprenant la description, le titre, l'importance, etc.
     */
    public function getSampleEventBaseData(string $type): array
    {
        $users = $this->retrieveEntities("user", $this);
        $sections = $this->retrieveEntities("section", $this);
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if (empty($sections)) {
            $sections = $this->em->getRepository(Section::class)->findAll();
        }
        return [
            "description" => $this->faker->sentence,
            "title"       => $this->faker->sentence,
            "isImportant" => $this->faker->boolean,
            "side"        => $this->faker->randomElement(['kitchen', 'office']),
            "type"        => $type,
            "createdBy"   => $this->faker->randomElement($users)->getFullName(),
            "updatedBy"   => $this->faker->randomElement($users)->getFullName(),
            "section"     => $sections[array_rand($sections)],
        ];
    }
    /**
     * Définit les attributs de base d'un événement à partir d'un tableau de données.
     *
     * @param Event $event L'événement à configurer.
     * @param array $data Les données de base pour remplir les attributs de l'événement (titre, description, etc.).
     * @return Event L'événement mis à jour avec les données fournies.
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
     * Définit les horodatages et le statut de date pour un événement en fonction d'un nombre de jours à ajouter.
     * Calcule la date d'échéance, le jour actif, et le statut de la date (plage active, passé, futur).
     *
     * @param int $day Le nombre de jours à ajouter à la date actuelle pour définir la date d'échéance.
     * @param Event $event L'événement dont les horodatages et le statut sont à configurer.
     * @return Event L'événement avec les horodatages et le statut de date mis à jour.
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

    //!  -------------------------------------------------------------------------------------------------------------




    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

}
