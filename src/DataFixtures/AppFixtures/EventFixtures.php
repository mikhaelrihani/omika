<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\event\Event;
use App\Entity\event\EventFrequence;
use App\Entity\event\EventSection;
use App\Entity\event\EventTask;
use App\Entity\event\EventInfo;
use App\Entity\event\Issue;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class EventFixtures extends BaseFixtures implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));

        // Créer les sections d'événements
        $this->createEventSections();
        $this->em->flush();

        // Créer les événements
        $this->createEvents(30);

        // Créer des Issues (ici en tant qu'exemple)
        $this->createIssues(10);

        $this->em->flush();
    }

    public function createEventSections(): void
    {
        $timestamps = $this->faker->createTimeStamps();
        $eventSections = $this->faker->getEventSectionList();
        $s = 0;

        foreach ($eventSections as $section) {
            $eventSection = new Section();
            $eventSection->setName($section);
            $eventSection->setCreatedAt($timestamps[ 'createdAt' ]);
            $eventSection->setUpdatedAt($timestamps[ 'updatedAt' ]);
            $this->em->persist($eventSection);
            $this->addReference("eventSection_{$s}", $eventSection);
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

    public function createEventFrequence(Event $event): EventFrequence
    {
        $weekDays = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"];
        $eventFrequence = new EventFrequence();

        $everyday = $this->faker->boolean;
        $selectedWeekDays = $this->faker->randomElements($weekDays, rand(1, 7));
        $monthDay = $this->faker->numberBetween(1, 31);

        // Choix aléatoire de la fréquence
        $frequencesChoice = [$everyday, $monthDay, $selectedWeekDays];
        $randomChoice = $this->faker->randomElement($frequencesChoice);

        if ($randomChoice == $everyday) {
            $eventFrequence->setEveryday(true);
        } elseif ($randomChoice == $selectedWeekDays) {
            $eventFrequence->setWeekDays($selectedWeekDays);
        } else {
            $eventFrequence->setMonthDay($monthDay);
        }

        $eventFrequence->setCreatedAt($event->getCreatedAt());
        $eventFrequence->setUpdatedAt($event->getUpdatedAt());

        return $eventFrequence;
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
