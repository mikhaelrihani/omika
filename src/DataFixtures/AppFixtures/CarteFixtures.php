<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\Carte\DishCategory;
use App\Entity\Carte\Dod;
use App\Entity\Carte\Menu;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Class CarteFixtures
 * Fixture class responsible for loading carte-related data into the database.
 * This class creates and persists dish categories, menus, and daily offers (DOD) in the database.
 */
class CarteFixtures extends BaseFixtures implements DependentFixtureInterface
{

    public function load(ObjectManager $manager): void
    {
        $this->createDishCategories();
        $this->createMenus();
        $this->createDod();

    }

    public function createDishCategories()
    {
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(DishCategory::class)->count([]) === 0) {

            $pictures = $this->retrieveEntities("picture", $this);
            $timestamps = $this->faker->createTimeStamps();

            for ($d = 0; $d < 10; $d++) {
                $dishCategory = new DishCategory();
                $dishCategory
                    ->setName($this->faker->word)
                    ->setCreatedAt($timestamps[ 'createdAt' ])
                    ->setUpdatedAt($timestamps[ 'updatedAt' ]);

                $this->setPicture($dishCategory, $pictures);

                $this->em->persist($dishCategory);
                $this->addReference("dishCategories_{$d}", $dishCategory);
            }
        }

    }

    public function createMenus()
    {
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(Menu::class)->count([]) === 0) {

            $users = $this->retrieveEntities("user", $this);

            for ($m = 0; $m < 10; $m++) {
                // Calculate the start date of the corresponding week
                $weekStartDate = (new \DateTime())->setISODate((int) date('Y'), $m + 1);

                // Define timestamps based on the week
                // setTime avec Faker permet d'ajouter un élément d'aléatoire aux heures d'un même jour, utile pour simuler des événements à des horaires variables tout en conservant la même date.
                $createdAt = \DateTimeImmutable::createFromMutable((clone $weekStartDate)->setTime($this->faker->numberBetween(0, 23), $this->faker->numberBetween(0, 59)));
                $updatedAt = (clone $createdAt)->modify('+' . $this->faker->numberBetween(0, 7) . ' days');

                $author = $this->faker->randomElement($users);

                $menu = new Menu();
                $menu
                    ->setWeek($m + 1)
                    ->setAuthor($author->getFullName())
                    ->setFishGrill($this->faker->word)
                    ->setMeatGrill($this->faker->word)
                    ->setChefSpecial($this->faker->word)
                    ->setSpecial($this->faker->word)
                    ->setStatus($this->faker->randomElement(['published', 'draft']))
                    ->setPdfPath($this->faker->unique()->url())
                    ->setCreatedAt($createdAt)
                    ->setUpdatedAt($updatedAt);

                $this->em->persist($menu);
                $this->addReference("menus_{$m}", $menu);
            }
            $this->em->flush();
        }

    }

    /**
     * Create Daily Offers (DOD) 
     */
    public function createDod()
    {
        $menus = $this->retrieveEntities("menus", $this);
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if (!empty($menus)) {
            foreach ($menus as $menu) {
                // Retrieve the creation and update timestamps of the current menu.
                // These timestamps will be used for the DODs to ensure that the creation and update dates of the DODs
                // are consistent with those of the menu to which they are associated.
                $createdAt = $menu->getCreatedAt();
                $updatedAt = $menu->getUpdatedAt();

                for ($d = 0; $d < 5; $d++) {
                    $dod = new Dod();
                    $dod
                        ->setMenu($menu)
                        ->setName($this->faker->word)
                        ->setDescription($this->faker->sentence)
                        ->setInfos($this->faker->sentence)
                        ->setOrderDay($d + 1)
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt($updatedAt);

                    $this->em->persist($dod);
                }
            }
            $this->em->flush();
        }


    }

    /**
     * Get the dependencies for this fixture.
     *
     * Specifies the fixture classes that this fixture depends on. This ensures that dependent fixtures
     * (such as MediaFixtures and UserFixtures) are loaded before this fixture.
     *
     * @return array The array of fixture classes that this fixture depends on.
     */
    public function getDependencies()
    {
        return [
            MediaFixtures::class, // Ensure MediaFixtures is loaded first for picture references
            UserFixtures::class,  // Ensure UserFixtures is loaded first for user references
        ];
    }
}