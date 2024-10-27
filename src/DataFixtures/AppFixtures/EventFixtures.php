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
use PhpParser\Node\Stmt\For_;

class EventFixtures extends BaseFixtures implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));

        // Créer les sections d'événements
        $this->createSections();
        $this->em->flush();

        // Créer les événements
        $this->createEvents(30);

        // Créer des Issues (ici en tant qu'exemple)
        $this->createIssues();

        $this->em->flush();
    }

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
            $this->addReference("eventSection_{$s}", $Section);
            $s++;
        }
    }

    public function createEvents($numEvents): void
    {
        $eventSections = $this->retrieveEntities("eventSection", $this);
        $users = $this->retrieveEntities("user", $this);

        for ($e = 0; $e < $numEvents; $e++) {
            $timestamps = $this->faker->createTimeStamps();
            $createdAt = $timestamps[ 'createdAt' ];
            $updatedAt = $timestamps[ 'updatedAt' ];
            $unlimited = $this->faker->boolean(20);
            $daysToAdd = $this->faker->numberBetween(1, 7); // Générer un nombre aléatoire de jours entre 1 et 7
            $dateLimit = $createdAt->modify('+1 month')->modify("+$daysToAdd days"); // Ajouter ces jours à la date d'un mois
            $activeDayRange = $this->faker->randomElements(range(-3, 7), rand(3, 11));
            $author = $this->faker->randomElement($users);


            // Créer un nouvel Event
            $event = new Event();
            $event
                ->setIsRecurring($this->faker->boolean(10))
                ->setType($this->faker->randomElement(['info', 'task']))
                ->setImportance($this->faker->boolean)
                ->setSharedWith($this->faker->randomElements($users, rand(1, 3))) // Ajoute un tableau de 1 à 3 utilisateurs unique 
                ->setDateCreated($createdAt)
                ->setDateLimit($dateLimit)
                ->setStatus($this->faker->randomElement(['published', 'draft']))
                ->setDescription($this->faker->sentence)
                ->setEventSection($this->faker->randomElement($eventSections))
                ->setAuthor($author->getFullName())
                ->setSide($this->faker->randomElement(['kitchen', 'office']))
                ->setPeriodeStart($updatedAt)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setPeriodeUnlimited($unlimited);
            $event->setActiveDayRange($activeDayRange);


            if (!$unlimited) {
                $event->setPeriodeEnd((clone $updatedAt)->modify('+' . $this->faker->numberBetween(1, 7) . ' days'));
            }

            // Création et association de la fréquence
            $eventFrequence = $this->createEventFrequence($event);
            $event->setEventFrequence($eventFrequence);

            // Gestion de la création d'EventTask ou EventInfo en fonction du type
            if ($event->getType() === 'task') {
                // Récupérer le nom de la section liée à l'événement
                $sectionName = $event->getEventSection()->getName();

                // Créer un nouvel EventTask
                $taskStatusActiveRange = [];
                $taskStatusOffRange = [];
                foreach ($activeDayRange as $activeDay) {
                    $status = $this->faker->randomElement(['done', 'todo', 'pending', 'late']);
                    $taskStatusActiveRange[$activeDay] = $status;
                    $taskStatusOffRange[] = $status;
                }
                ;

                $eventTask = new EventTask();
                $eventTask->setTaskDetails($sectionName . ': ' . $this->faker->word()) // Ajout du nom de la section devant le texte aléatoire
                    ->setTaskStatusActiveRange($taskStatusActiveRange)
                    ->setTaskStatusOffRange($taskStatusOffRange)
                    ->setEvent($event);

                $this->em->persist($eventTask);
                $event->setEventTask($eventTask);
            } else {
                $eventInfo = new EventInfo();
                $tagInfoActiveRange = [];
                foreach ($activeDayRange as $activeDay) {
                    $status = $this->faker->randomElement(['unread', 'read']);
                    $tagInfoActiveRange[$activeDay] = $status;
                }
                ;
                $eventInfo
                    ->setTagInfoActiveRange($tagInfoActiveRange)
                    ->setTagInfoOffRange([$this->faker->randomElement(['read', 'archived'])])
                    ->setReadUsers([$author->getFullName()])
                    ->setEvent($event);
                $this->em->persist($eventInfo);
                $event->setEventInfo($eventInfo);
            }

            // Persister l'événement et sa fréquence
            $this->em->persist($eventFrequence);
            $this->em->persist($event);
        }
    }

    public function createEventRecurring($numEventsRecurring): void
{
    for ($e = 0; $e < $numEventsRecurring; $e++) {

        // Assure l'ordre chronologique : createdAt < periodeStart < updatedAt < periodeEnd.
        $timestamps = $this->faker->createTimeStamps();
        $createdAt = $timestamps['createdAt'];
        $updatedAt = $timestamps['updatedAt'];

        // Génère un periodeStart entre createdAt et updatedAt
        $periodeStart = $this->faker->dateTimeImmutableBetween($createdAt->format('Y-m-d H:i:s'), $updatedAt->format('Y-m-d H:i:s'));
        // Génère un periodeEnd entre updatedAt et maintenant (now)
        $periodeEnd = $this->faker->dateTimeImmutableBetween($updatedAt->format('Y-m-d H:i:s'), "now");

        $eventRecurring = new EventRecurring();
        $eventRecurring
            ->setPeriodeStart($periodeStart)
            ->setPeriodeEnd($periodeEnd);

        // Détermine si l'événement est quotidien ou non
        $everyday = rand(0, 1);

        if (!$everyday) {
            // Choisir au hasard l’un des trois types de récurrence : jours du mois, jours de la semaine ou dates spécifiques.
            $recurrenceType = rand(1, 3); // 1 = jours du mois, 2 = jours de la semaine, 3 = dates spécifiques
            $randomIndex = rand(1, 4);

            if ($recurrenceType === 1) {  // Choix des jours du mois
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
            } elseif ($recurrenceType === 2) {  // Choix des jours de la semaine
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
            } else {  // Choix de dates spécifiques
                $periodDates = [];
                $randomIndex = rand(3, 10);
                for ($i = 0; $i < $randomIndex; $i++) {
                    $randomDate = new DateTimeImmutable();  // Crée une nouvelle date aléatoire
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

        $eventRecurring->setCreatedAt($createdAt);
        $eventRecurring->setUpdatedAt($updatedAt);

        $this->em->persist($eventRecurring);
    }
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
