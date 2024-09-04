<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Class recipeFixtures
 *
 * Fixture class responsible for loading recipe-related data into the database.
 */
class RecipeFixtures extends BaseFixtures implements DependentFixtureInterface
{
   
    /**
     * Load the recipe fixtures into the database.
     */
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));
        $this->em->flush();
    }
   

    
    
    /**
     * Get the dependencies for this fixture.
     *
     * @return array The array of fixture classes that this fixture depends on.
     */
    public function getDependencies()
    {
        return [
            ProductFixtures::class,
        ];
    }}


