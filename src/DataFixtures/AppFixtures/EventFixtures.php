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
use DateInterval;
use DateTimeImmutable;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;


class EventFixtures extends BaseFixtures implements DependentFixtureInterface
{


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
        $this->createEvents(50);
        $this->em->flush();
        // // Créer les événements récurrence parents
        $this->createEventRecurringParent();
        $this->em->flush();
        // // Créer les événements enfants pour chaque événement récurrence parent
        $this->createEventsChildrenforEachEventRecurringParent();
        $this->em->flush();

    }
    /**
     * Crée des incidents (issues) et les enregistre dans la base de données.
     *
     * Cette méthode génère une liste de 30 incidents en assignant des valeurs aléatoires aux propriétés
     * de chaque incident, y compris l'auteur, les techniciens concernés, les dates, et d'autres détails.
     * Chaque incident est créé avec des informations de suivi et un numéro d'identification unique.
     * 
     * La méthode utilise un utilisateur aléatoire comme auteur et attribue des techniciens ayant
     * le titre "technicien" aux rôles de technicien contacté et technicien à venir.
     *
     * @return void
     */
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
     * Crée des sections et les enregistre dans la base de données.
     *
     * Cette méthode génère plusieurs sections, chaque section ayant un nom défini dans la liste
     * retournée par `getSectionList`. Chaque section reçoit également des timestamps de création et
     * de mise à jour aléatoires. Un identifiant de référence unique est ajouté pour chaque section
     * pour une utilisation ultérieure.
     *
     * @return void
     */
    public function createSections(): void
    {
        $timestamps = $this->faker->createTimeStamps();

        $Sections = $this->faker->getSectionList();
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
            $timestamps = $this->faker->createTimeStamps('-15 days', 'now');
            $createdAt = $timestamps[ 'createdAt' ];
            $updatedAt = $timestamps[ 'updatedAt' ];
            $now = new DateTimeImmutable('now');
            $randomDay = rand(1, 30);
            $dueDate = $this->faker->dateTimeImmutableBetween(

                $updatedAt->format('Y-m-d H:i:s'),
                $updatedAt->modify("+{$randomDay} days")->format('Y-m-d H:i:s')
            );
            // Créer une version sans heure de $dueDate et $now
            $dueDateDayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $dueDate->format('Y-m-d'));
            $nowDayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $now->format('Y-m-d'));

            // Calculer la différence en jours entiers
            $activeDayInt = (int) $nowDayOnly->diff($dueDateDayOnly)->format('%r%a');
            // Limite $activeDayInt entre -3 et 7
            $activeDay = ($activeDayInt >= -3 && $activeDayInt <= 7) ? $activeDayInt : null;

            // Calculer la différence pour déterminer le statut de la date
            $dueDateDiff = (int) $nowDayOnly->diff($dueDateDayOnly)->format('%r%a'); // Convertir en entier

            if ($dueDateDiff >= -3 && $dueDateDiff <= 7) {
                $dateStatus = "activeDayRange";
            } elseif ($dueDateDiff >= -30 && $dueDateDiff < -3) {
                $dateStatus = "past";
            } else {
                $dateStatus = "future";
            }

            $event = $this->setEventBase();
            $event
                ->setIsRecurring(False)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setActiveDay($activeDay)
                ->setDueDate($dueDate)
                ->setDateStatus(
                    $dateStatus
                );

            $this->setEventRelations($event);
            $this->em->persist($event);
        }
    }
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

        for ($e = 0; $e < 5; $e++) {
            $timestamps = $this->faker->createTimeStamps('-15 days', 'now');
            $createdAt = $timestamps[ 'createdAt' ];
            $updatedAt = $timestamps[ 'updatedAt' ];

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
                $eventRecurring->setEveryday(False);
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
            }

            $this->em->persist($eventRecurring);
            $this->addReference("eventRecurring_{$e}", $eventRecurring);
        }
        $this->em->flush();

    }

    /**
     * Crée des événements enfants pour chaque événement récurrent parent.
     *
     * Cette méthode génère des événements enfants pour chaque événement récurrent en
     * récupérant les propriétés de l'événement parent. Les événements enfants sont créés
     * selon les dates de récurrence définies, y compris les jours de la semaine, les jours
     * du mois et les dates spécifiques. Les événements sont filtrés en fonction de
     * l'intervalle de création actif.
     *
     * @return void
     */
    public function createEventsChildrenforEachEventRecurringParent(): void
    {
        $eventsRecurring = $this->retrieveEntities("eventRecurring", $this);

        foreach ($eventsRecurring as $eventRecurring) {

            // Retrieve properties from the parent event
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
            $endDate = $eventRecurring->getPeriodeEnd();
            $endDate = DateTimeImmutable::createFromFormat(
                'Y-m-d',
                ($eventRecurring->getPeriodeEnd() ?? $latestCreationDate)->format('Y-m-d')
            );// si l'event est illimité,car dans ce cas la period date est null.


            // Vérifier si la période de l'événement parent chevauche la période active
            if ($endDate < $earliestCreationDate || $startDate > $latestCreationDate) {
                continue; // Aucun événement enfant à créer pour ce parent
            }

            // Handle everyday events
            if ($everyday) {
                // Ajuster les dates de début et de fin possibles pour les enfants en fonction de la période active
                $firstDueDate = ($startDate > $earliestCreationDate) ? $startDate : $earliestCreationDate;
                $lastDueDate = ($endDate < $latestCreationDate) ? $endDate : $latestCreationDate;
                $numberOfEventsChildren = (int) $firstDueDate->diff($lastDueDate)->format('%r%a') + 1;
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
    public function duplicateEventProperties(Event $originalEvent): Event
    {
        // Create a new instance of Section for the duplicated event
        $newSection = new Section();

        // Retrieve the section name from the original event
        $sectionName = $originalEvent->getSection() ? $originalEvent->getSection()->getName() : null;
        $this->em->persist($newSection);
        // Create a new Event instance
        $newEvent = new Event();

        // Copy properties from the original event to the new event
        $newEvent->setIsRecurring($originalEvent->isRecurring());
        $newEvent->setSide($originalEvent->getSide());
        $newEvent->setType($originalEvent->getType());
        $newEvent->setTitle($originalEvent->getTitle());
        $newEvent->setDescription($originalEvent->getDescription());
        $newEvent->setCreatedBy($originalEvent->getCreatedBy());
        $newEvent->setUpdatedBy($originalEvent->getUpdatedBy());
        $newEvent->setIsImportant($originalEvent->isImportant());

        // Set the new section with the name from the original event
        if ($sectionName) {
            $newSection->setName($sectionName);
            $newEvent->setSection($newSection);
        }

        return $newEvent;
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
    public function duplicateEvent(Event $event): Event
    {
        // Définir la nouvelle date d'échéance et les dates de création/mise à jour en ajoutant un jour
        $dueDate = $event->getDueDate()->modify('+1 day');
        $createdAt = $dueDate;
        $updatedAt = $dueDate;

        // Définir le contexte de la date actuelle sans l'heure
        $now = new DateTimeImmutable('now');
        $dueDateDayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $dueDate->format('Y-m-d'));
        $nowDayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $now->format('Y-m-d'));

        // Calculer la différence en jours entiers entre la date actuelle et la nouvelle date d'échéance
        $dueDateDiff = (int) $nowDayOnly->diff($dueDateDayOnly)->format('%r%a');

        // Déterminer le statut de la date et la valeur d'activeDay en fonction de cette différence
        if ($dueDateDiff >= -3 && $dueDateDiff <= 7) {
            $dateStatus = "activeDayRange";
            $activeDay = $dueDateDiff;
        } elseif ($dueDateDiff >= -30 && $dueDateDiff < -3) {
            $dateStatus = "past";
            $activeDay = null; // `activeDay` n'est défini que pour l'intervalle `-3 à 7`
        } else {
            $dateStatus = "future";
            $activeDay = null;
        }

        // Créer le nouvel événement en copiant les propriétés de l'original et en appliquant les nouvelles valeurs
        $newEvent = $this->duplicateEventProperties($event);
        $newEvent
            ->setDateStatus($dateStatus)
            ->setActiveDay($activeDay)
            ->setDueDate($dueDate)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt);

        // Persister l'événement et le retourner
        $this->em->persist($newEvent);
        return $newEvent;
    }


    /**
     * Duplique un événement en attente pour un certain nombre de jours.
     *
     * Cette méthode crée plusieurs duplicatas d'un événement en attente pour un nombre
     * de jours déterminé aléatoirement. Le statut de l'événement final est défini sur "done"
     * si c'est le dernier jour de duplication, sinon il est défini sur "pending".
     *
     * @param Event $event L'événement à dupliquer en attente.
     *
     * @return void
     */
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
            $this->em->persist($newEvent);

            // on flushe a chaque event pour pouvoir traiter les status unrealised de chaque event enfant dans la periode -30 a -1.
            $this->em->flush();

            // Met à jour l'événement courant pour la prochaine itération
            $event = $newEvent;
            $i++;
        }
    }

    /**
     * Duplique un événement récurrent en attente en vérifiant les événements frères.
     *
     * Cette méthode duplique un événement récurrent en attente, en vérifiant si un frère
     * existe le jour suivant. Si un frère est trouvé, l'événement actuel est marqué comme
     * "unrealised". Sinon, plusieurs duplicatas sont créés, en ajustant les statuts
     * en fonction de la présence d'événements frères.
     *
     * @param Event $event L'événement récurrent à dupliquer.
     *
     * @return void
     */
    public function duplicatePendingRecurringEvent(Event $event, EventRecurring $eventRecurring): void
    {
        $eventsBrothers = $eventRecurring->getEvents();

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
                    $eventRecurring->addEvent($newEvent);
                    break;
                } else {
                    // Sinon, on continue avec le statut "pending" ou "done" pour le dernier jour
                    if ($i === $numberOfPendingDays) {
                        $this->setEventTask($newEvent, 'done');
                    } else {
                        $this->setEventTask($newEvent, 'pending');
                    }
                    // Ajoute et persiste le nouvel événement
                    $this->em->persist($newEvent);
                    $this->em->flush();
                    $eventRecurring->addEvent($newEvent);
                }
                // Passe au prochain événement
                $event = $newEvent;
                $i++;
            }
        }


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
    public function duplicateUnrealisedRecurringEvent(Event $event, EventRecurring $eventRecurring, $RangeStart = "-30 days", $RangeEnd = "-1 day")
    {
        $dateRangeStart = new DateTimeImmutable($RangeStart);
        $dateRangeEnd = new DateTimeImmutable($RangeEnd);

        // Exclure l'événement actuel des frères
        $eventsBrothers = $eventRecurring->getEvents()->filter(function ($brotherEvent) use ($event) {
            return $brotherEvent !== $event;
        });

        // Vérifier si, après exclusion, la liste est vide
        if ($eventsBrothers->isEmpty()) {
            // Si aucun frère n'existe, dupliquer jusqu'à créer un événement 'done'
            $nextDueDate = $event->getDueDate()->modify('+1 day');

            while (true) {
                $newEvent = $this->duplicateEvent($event);
                $taskStatus = $this->faker->randomElement(['unrealised', 'done']);
                $this->setEventTask($newEvent, $taskStatus);
                $newEvent->setDueDate($nextDueDate);
                $this->em->persist($newEvent);
                $this->em->flush();

                $eventRecurring->addEvent($newEvent);

                if ($taskStatus === 'done') {
                    break;
                }

                // Préparer la prochaine date pour le prochain événement à dupliquer
                $nextDueDate = $nextDueDate->modify('+1 day');
            }
        } else {
            // Si des frères existent, filtrer et gérer selon la plage donnée
            $eventsBrothersInRange = $eventsBrothers->filter(function ($eventBrother) use ($dateRangeStart, $dateRangeEnd) {
                return $eventBrother->getTask()->getTaskStatus() === "unrealised"
                    && $eventBrother->getDueDate() >= $dateRangeStart
                    && $eventBrother->getDueDate() <= $dateRangeEnd;
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
                    $newEvent = $this->duplicateEvent($eventBrother);
                    $taskStatus = $this->faker->randomElement(['unrealised', 'done']);
                    $this->setEventTask($newEvent, $taskStatus);
                    $this->em->persist($newEvent);
                    $this->em->flush();
                    $eventRecurring->addEvent($newEvent);

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

        $this->em->persist($event);
        return $event;

    }

    /**
     * set the event type property for the given event depending on the type.
     *
     * @param Event $event The event entity to set the type for.
     *
     * @return void
     */
    public function setEventRelations(Event $event, EventRecurring $eventRecurring = null): void
    {

        $createdAt = $event->getCreatedAt();
        $updatedAt = $event->getUpdatedAt();
        $now = new DateTimeImmutable('now');
        $nowDayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $now->format('Y-m-d'));

        $type = $event->getType();
        // Traitement du type Task
        if ($type === "task") {
            // Obtention de la dueDate et calcul de la différence
            $dueDate = $event->getDueDate();
            $dueDateDayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $dueDate->format('Y-m-d'));
            $dueDateDiff = (int) $nowDayOnly->diff($dueDateDayOnly)->format('%r%a');

            // Cas des événements non récurrents, dans la plage active (-30 jours à aujourd'hui non inclus)
            if (!$eventRecurring && $dueDateDiff >= -30 && $dueDateDiff < 0) {
                $taskStatut = $this->faker->randomElement(['done', 'unrealised']);

                // Si l'événement a un statut "unrealised"
                if ($taskStatut === 'unrealised') {
                    $this->setEventTask($event, 'unrealised');
                    $this->em->persist($event);
                    $this->em->flush();
                    do {
                        $newEvent = $this->duplicateEvent($event);
                        $newDueDate = $newEvent->getDueDate();
                        $newDueDateDayOnly = DateTimeImmutable::createFromFormat('Y-m-d', $newDueDate->format('Y-m-d'));
                        // Vérifie si la date d'échéance du nouvel événement est hier et définit le statut sur "done" si c'est le cas
                        if ($newDueDateDayOnly === $nowDayOnly->modify('-1 day')) {
                            $taskStatus = 'late';  // Met à jour taskStatus pour arrêter la boucle 
                            $this->setEventTask($newEvent, $taskStatus);
                        } else {
                            $taskStatus = $this->faker->randomElement(['unrealised', 'done']);
                            $this->setEventTask($newEvent, $taskStatus);
                        }
                        $this->em->persist($newEvent);
                        $this->em->flush();
                        // Met à jour l'événement courant pour la prochaine itération
                        $event = $newEvent;

                    } while ($taskStatus === 'unrealised');
                } else {
                    $this->setEventTask($event, 'done');
                    $this->em->persist($event);
                    $this->em->flush();

                }

                // Cas des événements récurrents dans la plage active (-30 jours à aujourd'hui non inclus)
            } elseif ($eventRecurring && $dueDateDiff >= -30 && $dueDateDiff < 0) {
                $taskStatut = $this->faker->randomElement(['done', 'unrealised']);
                if ($taskStatut === 'unrealised') {
                    $this->setEventTask($event, 'unrealised');
                    $eventRecurring->addEvent($event);
                    $this->duplicateUnrealisedRecurringEvent($event, $eventRecurring);
                } else {
                    $this->setEventTask($event, 'done');
                    $eventRecurring->addEvent($event);
                }

                // Cas où la dueDate est aujourd'hui
            } elseif ($dueDateDiff >= 0 && $dueDateDiff <= 7) {
                $eventRecurring ?
                    $taskStatuses = ['todo', 'todo_modified', 'done', 'pending', 'warning'] :
                    $taskStatuses = ['todo', 'todo_modified', 'done', 'pending'];
                    
                $this->handleEventStatus($event, $taskStatuses, $eventRecurring);

                // Cas des événements futurs (dueDate dans le futur)
            } elseif ($dueDateDiff > 7) {
                $eventRecurring ?
                    $taskStatuses = ['todo_modified', 'done', 'pending', 'warning'] :
                    $taskStatuses = ['todo_modified', 'done', 'pending'];

                $this->handleEventStatus($event, $taskStatuses, $eventRecurring);
            }
            // Traitement du type Info
        } elseif ($type === "info") {
            $users = $this->retrieveEntities("user", $this);
            $usersCount = count($users);
            $randomNumOfUsers = $this->faker->numberBetween(1, $usersCount);
            $randomUsers = $this->faker->randomElements($users, $randomNumOfUsers);

            $info = new EventInfo();
            $inforeadCounter = 0;

            foreach ($randomUsers as $user) {
                $eventSharedInfo = new EventSharedInfo();
                $isRead = $this->faker->boolean;

                if ($isRead) {
                    $inforeadCounter++;
                }
                $eventSharedInfo
                    ->setUser($user)
                    ->setEventInfo($info)
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

            if ($inforeadCounter === $randomNumOfUsers) {
                $info->setIsFullyRead(true);
            } else {
                $info->setIsFullyRead(false);
            }

            $this->em->persist($info);
            $event->setInfo($info);
            $this->em->persist($event);
            $this->em->flush();
            if ($eventRecurring) {
                $eventRecurring->addEvent($event);
            }


        }
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
        $this->em->persist($event);
        $this->em->flush();
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
        $this->em->persist($event);
        $this->em->flush();
        if ($eventRecurring) {
            $eventRecurring->addEvent($event);
        }
        if ($taskStatut === 'pending' && $eventRecurring) {
            $this->duplicatePendingRecurringEvent($event, $eventRecurring);
        }
        if ($taskStatut === 'pending') {
            $this->duplicatePendingEvent($event);
        }
    }


    /**
     * Process the event based on its due date and current time.
     *
     * @param Event $event The event entity to process.
     * @param DateTimeImmutable $now The current date and time.
     *
     * @return void
     */

    /**
     * Calculate the number of everyday child events.
     *
     * @param DateTimeImmutable $firstDueDate The first due date for the child events.
     * @param DateTimeImmutable|null $lastDueDate The last due date for the child events, if any.
     * @param DateTimeImmutable $latestCreationDate The latest date of creation to consider// 7 days from now.
     * @param DateTimeImmutable|null $endDate The period end date for the calculations, if applicable.
     *
     * @return int The number of everyday child events calculated.
     */


    /**
     * Create child events for everyday cases.
     *
     * @param mixed $eventRecurring The parent recurring event to associate with the child events.
     * @param DateTimeImmutable $firstDueDate The first due date for the child events.
     * @param int $numberOfEventsChildren The number of child events to create.
     * @param DateTimeImmutable $createdAtParent The creation date of the parent event.
     * @param DateTimeImmutable $now The current date and time.
     *
     * @return void
     */
    private function createChildEvents($eventRecurring, DateTimeImmutable $firstDueDate, int $numberOfEventsChildren, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {

        for ($i = 0; $i < $numberOfEventsChildren; $i++) {
            $dueDate = $firstDueDate->modify("+{$i} days");
            [$createdAt, $updatedAt] = $this->calculateCreatedUpdatedDates($dueDate, $createdAtParent, $now);
            [$dateStatus, $activeDay] = $this->calculateDateStatus($dueDate, $now);

            $this->persistEventRecurrring($eventRecurring, $dueDate, $createdAt, $updatedAt, $dateStatus, $activeDay);

        }
    }

    /**
     * Handle a period date and create the corresponding child event.
     *
     * @param EventRecurring $eventRecurring The parent recurring event entity.
     * @param DateTimeImmutable $dueDate The due date for the child event.
     * @param DateTimeImmutable $createdAtParent The creation date of the parent event.
     * @param DateTimeImmutable $now The current date.
     *
     * @return void
     */
    private function handlePeriodDate($eventRecurring, DateTimeImmutable $dueDate, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        $dueDateInt = (int) $now->diff($dueDate)->format('%r%a');

        // Check active range validity
        if ($dueDateInt > 7 || $dueDateInt < -30) {
            return; // Exclude dates outside of active range
        } else {
            [$createdAt, $updatedAt] = $this->calculateCreatedUpdatedDates($dueDate, $createdAtParent, $now);
            [$dateStatus, $activeDay] = $this->calculateDateStatus($dueDate, $now);
            $this->persistEventRecurrring($eventRecurring, $dueDate, $createdAt, $updatedAt, $dateStatus, $activeDay);

        }


    }

    /**
     * Handle a month day and create the corresponding child event.
     *
     * @param EventRecurring $eventRecurring The parent recurring event entity.
     * @param DateTimeImmutable $date The target month day date for the child event.
     * @param DateTimeImmutable $createdAtParent The creation date of the parent event.
     * @param DateTimeImmutable $now The current date.
     *
     * @return void
     */
    private function handleMonthDay($eventRecurring, DateTimeImmutable $date, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        $isActiveDay = (int) $now->diff($date)->format('%r%a');
        [$createdAt, $updatedAt] = $this->calculateCreatedUpdatedDates($date, $createdAtParent, $now);
        [$dateStatus, $activeDay] = $this->calculateDateStatus($date, $now);

        $this->persistEventRecurrring($eventRecurring, $date, $createdAt, $updatedAt, $dateStatus, $activeDay);
    }

    /**
     * Handle a week day and create the corresponding child event.
     *
     * @param EventRecurring $eventRecurring The parent recurring event entity.
     * @param DateTimeImmutable $date The target week day date for the child event.
     * @param DateTimeImmutable $createdAtParent The creation date of the parent event.
     * @param DateTimeImmutable $now The current date.
     *
     * @return void
     */
    private function handleWeekDay($eventRecurring, DateTimeImmutable $date, DateTimeImmutable $createdAtParent, DateTimeImmutable $now): void
    {
        $isActiveDay = (int) $now->diff($date)->format('%r%a');
        [$createdAt, $updatedAt] = $this->calculateCreatedUpdatedDates($date, $createdAtParent, $now);
        [$dateStatus, $activeDay] = $this->calculateDateStatus($date, $now);

        $this->persistEventRecurrring($eventRecurring, $date, $createdAt, $updatedAt, $dateStatus, $activeDay);
    }

    /**
     * Calculate createdAt and updatedAt dates based on due date and parent's createdAt.
     *
     * @param DateTimeImmutable $dueDate The due date for the child event.
     * @param DateTimeImmutable $createdAtParent The creation date of the parent event.
     *
     * @return array An array containing the calculated [createdAt, updatedAt] dates.
     */
    private function calculateCreatedUpdatedDates(DateTimeImmutable $dueDate, DateTimeImmutable $createdAtParent, $now): array
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

    /**
     * Calculate the status of the date based on the current time.
     *
     * @param DateTimeImmutable $date The date to evaluate, soit due date
     * @param DateTimeImmutable $now The current date.
     *
     * @return array An array containing the calculated [dateStatus, activeDay].
     */
    private function calculateDateStatus(DateTimeImmutable $dueDate, DateTimeImmutable $now): array
    {

        // ici on doit comprendre que le cronjob met a jour les events chaque jour, donc on ne peut pas se baser sur la date de creation pour determiner le statut de la date.
        $activeDayInt = (int) $now->diff($dueDate)->format('%r%a');
        $dateStatus = ($activeDayInt >= -3) ? "activeDayRange" : "past";
        $activeDay = ($activeDayInt >= -3) ? $activeDayInt : null;
        return [$dateStatus, $activeDay];
    }

    /**
     * Persist an event to the database without the event having a Type and taskStatus set.
     *
     * @param EventRecurring $eventRecurring The parent recurring event entity.
     * @param DateTimeImmutable $dueDate The due date for the child event.
     * @param DateTimeImmutable $createdAt The creation date for the child event.
     * @param DateTimeImmutable $updatedAt The updated date for the child event.
     * @param string $dateStatus The status of the date for the child event.
     * @param int|null $activeDay The active day for the child event.
     *
     * @return void
     */
    private function persistEventRecurrring(EventRecurring $eventRecurring, DateTimeImmutable $dueDate, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt, string $dateStatus, ?int $activeDay): void//!
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

        $this->setEventRelations($event, $eventRecurring);

    }


    /**
     * Get the dependencies for this fixture.
     *
     * @return array An array of classes that this fixture depends on.
     */
    public function getDependencies(): array
    {
        return [
            CarteFixtures::class,
            UserFixtures::class,
        ];
    }

}
