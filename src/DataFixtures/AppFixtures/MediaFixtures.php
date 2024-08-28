<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use Doctrine\Persistence\ObjectManager;


class MediaFixtures extends BaseFixtures
{
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));

        // Créer les timestamps
        $createdAt = $this->faker->dateTimeImmutableBetween('-5 years', 'now');
        $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt, 'now');


    }
}