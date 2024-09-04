<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\event\Event;
use App\Entity\event\EventFrequence;
use App\Entity\event\EventSection;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Class EventFixtures
 *
 * Fixture class responsible for loading event-related data into the database.
 */
class EventFixtures extends BaseFixtures implements DependentFixtureInterface
{

    /**
     * Load the recipe fixtures into the database.
     */
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));

        $this->createEventSections();
        $this->em->flush();
        $this->createEventAndEventFrequences(30);
        $this->em->flush();

    }

    public function createEventFrequences(Event $event): EventFrequence
    {
        $weekDays = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"];
        $selectedWeekDays = [];

        $eventFrequence = new EventFrequence();

        $everyday = $this->faker->boolean;

        $selectedWeekDays = $this->faker->randomElements($weekDays, rand(1, 7));
        $monthDay = $this->faker->numberBetween(1, 31);

        // Choix aléatoire parmi les fréquences
        $frequencesChoice = [$everyday, $monthDay, $selectedWeekDays];
        $randomChoice = $this->faker->randomElement($frequencesChoice);

        // Attribution des valeurs à l'entité en fonction du choix
        if ($randomChoice == $everyday) {
            $eventFrequence->setEveryday(true);
            $eventFrequence->setWeekDays(null);
            $eventFrequence->setMonthDay(null);
        }
        if ($randomChoice == $selectedWeekDays) {
            $eventFrequence->setEveryday(null);
            $eventFrequence->setWeekDays($selectedWeekDays);
            $eventFrequence->setMonthDay(null);
        }
        if ($randomChoice == $monthDay) {
            $eventFrequence->setEveryday(null);
            $eventFrequence->setWeekDays(null);
            $eventFrequence->setMonthDay($monthDay);
        }

        $eventFrequence->setCreatedAt($event->getCreatedAt());
        $eventFrequence->setUpdatedAt($event->getUpdatedAt());

        return $eventFrequence;
    }
    public function createEventSections(): void
    {
        $timestamps = $this->faker->createTimeStamps();
        $s = 0;
        $eventSections = $this->faker->getEventSectionList();
        foreach ($eventSections as $section) {
            $eventSection = new EventSection();
            $eventSection->setName($section);
            $eventSection->setCreatedAt($timestamps[ 'createdAt' ]);
            $eventSection->setUpdatedAt($timestamps[ 'updatedAt' ]);
            $this->em->persist($eventSection);
            $this->addReference("eventSection_{$s}", $eventSection);
            $s++;
        }
    }

    public function createEventAndEventFrequences($numEvents): void
    {

        $eventSections = $this->retrieveEntities("eventSection", $this);
        $users = $this->retrieveEntities("user", $this);

        for ($e = 0; $e < $numEvents; $e++) {
            $timestamps = $this->faker->createTimeStamps();
            $createdAt = $timestamps[ 'createdAt' ];
            $updatedAt = $timestamps[ 'updatedAt' ];
            $unlimited = $this->faker->boolean(20);
            $author = $this->faker->randomElement($users);

            $event = new Event();
            $event
                ->setSide($this->faker->randomElement(['kitchen', 'office']))
                ->setVisible($this->faker->boolean(80))
                ->setEventSection($this->faker->randomElement($eventSections))
                ->setStatus($this->faker->randomElement(['published', 'draft']))
                ->setText($this->faker->text(200))
                ->setAuthor($author->getFullName())
                ->setType($this->faker->randomElement(["taches", "info"]))
                ->setPeriodeStart($updatedAt);
            if ($unlimited) {
                $event
                    ->setPeriodeUnlimited($unlimited)
                    ->setPeriodeEnd(null);
            } else {
                $event
                    ->setPeriodeUnlimited(null)
                    ->setPeriodeEnd((clone $updatedAt)->modify('+' . $this->faker->numberBetween(0, 7) . ' days'));
            }
            $event
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            $eventFrequence = $this->createEventFrequences($event);
            $event->setEventFrequence($eventFrequence);

            // Persistance de l'événement et de sa fréquence associée

            $this->em->persist($eventFrequence);
            $this->em->persist($event);

        }
    }

    /**
     * Get the dependencies for this fixture.
     *
     * @return array The array of fixture classes that this fixture depends on.
     */
    public function getDependencies()
    {
        return [
            CarteFixtures::class,
            UserFixtures::class,
        ];
    }
}


