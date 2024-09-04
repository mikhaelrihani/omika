<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\carte\DishCategory;
use App\Entity\carte\Dod;
use App\Entity\carte\Menu;
use App\Entity\user\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Class CarteFixtures
 *
 * Fixture class responsible for loading carte-related data into the database.
 */
class CarteFixtures extends BaseFixtures implements DependentFixtureInterface
{


    /**
     * Load the carte fixtures into the database.
     */
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));
        $this->createDishCategories();
        $this->createMenus();
        $this->em->flush();

        $this->createDod();
        $this->em->flush();
    }

    public function createDishCategories()
    {
        $pictures = $this->retrieveEntities("picture", $this);
        $timestamps = $this->faker->createTimeStamps();

        for ($d = 0; $d < 10; $d++) {
            $dishCategory = new DishCategory();
            $dishCategory->setName($this->faker->word);
            $this->setPicture($dishCategory, $pictures);
            $dishCategory->setCreatedAt($timestamps[ 'createdAt' ]);
            $dishCategory->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($dishCategory);
            $this->addReference("dishCategories_{$d}", $dishCategory);
        }


    }

    public function createMenus()
    {
        $users = $this->retrieveEntities("user", $this);

        for ($m = 0; $m < 10; $m++) {
            // Calculer la date de début de la semaine correspondante
            $weekStartDate = (new \DateTime())->setISODate((int) date('Y'), $m + 1);

            // Définir les timestamps en fonction de la semaine
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
    }

    public function createDod()
    {

        $menus = $this->retrieveEntities("menus", $this);


        foreach ($menus as $menu) {
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

    }




    /**
     * Get the dependencies for this fixture.
     *
     * @return array The array of fixture classes that this fixture depends on.
     */
    public function getDependencies()
    {
        return [
            MediaFixtures::class,
            UserFixtures::class,
        ];
    }
}


