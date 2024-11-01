<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\carte\Dish;
use App\Entity\recipe\Ingredient;
use App\Entity\recipe\Recipe;
use App\Entity\recipe\RecipeAdvise;
use App\Entity\recipe\RecipeStep;
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

        $this->createRecipes(30);
        $this->em->flush();

        $this->createRecipeStepsAndAdvises();
        $this->createDishes();
        $this->createIngredients();
        $this->em->flush();

    }


    public function createRecipes($numRecipes)
    {
        for ($r = 0; $r < $numRecipes; $r++) {
            $timestamps = $this->faker->createTimeStamps();
            $recipe = new Recipe();
            $recipe->setName($this->faker->unique()->text(30));
            $recipe->setPath($this->faker->url());
            $recipe->setCreatedAt($timestamps[ 'createdAt' ]);
            $recipe->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($recipe);
            $this->addReference("recipe_{$r}", $recipe);
        }
    }

    public function createRecipeStepsAndAdvises()
    {
        $recipes = $this->retrieveEntities("recipe", $this);

        foreach ($recipes as $recipe) {
            $numSteps = $this->faker->numberBetween(3, 7);
            for ($s = 0; $s < $numSteps; $s++) {

                $recipeStep = new RecipeStep();
                $recipeStep
                    ->setRecipe($recipe)
                    ->setOrderStep($s + 1)
                    ->setDescription($this->faker->sentence())
                    ->setCreatedAt($recipe->getCreatedAt())
                    ->setUpdatedAt($recipe->getUpdatedAt());

                $this->em->persist($recipeStep);
            }
            $this->createRecipeAdvises($recipe);
        }
    }

    public function createRecipeAdvises($recipe)
    {
        $numAdvises = $this->faker->numberBetween(1, 3);
        for ($a = 0; $a < $numAdvises; $a++) {

            $recipeAdvise = new RecipeAdvise();
            $recipeAdvise
                ->setRecipe($recipe)
                ->setOrderAdvise($a + 1)
                ->setDescription($this->faker->sentence())
                ->setCreatedAt($recipe->getCreatedAt())
                ->setUpdatedAt($recipe->getUpdatedAt());

            $this->em->persist($recipeAdvise);
        }
    }

    public function createDishes()
    {
        $recipes = $this->retrieveEntities("recipe", $this);
        $dishCategories = $this->retrieveEntities("dishCategories", $this);
        $pictures = $this->retrieveEntities("picture", $this);

        foreach ($recipes as $recipe) {
            $timestamps = $this->faker->createTimeStamps();
            $dish = new Dish();
            $this->setPicture($dish, $pictures);
            $dish
                ->setRecipe($recipe)
                ->setDishCategory($this->faker->randomElement($dishCategories))
                ->setName($this->faker->unique()->word())
                ->setNameGender($this->faker->randomElement(['male', 'female']))
                ->setSlug($this->faker->slug())
                ->setPrice($this->faker->randomFloat(2, 8, 30))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($dish);
        }
    }

    public function createIngredients()
    {
        $units = $this->retrieveEntities("unit", $this);
        $products = $this->retrieveEntities("product", $this);
        $recipes = $this->retrieveEntities("recipe", $this);

        foreach ($recipes as $recipe) {

            $numIngredients = $this->faker->numberBetween(3, 7);
            for ($i = 0; $i < $numIngredients; $i++) {
                // Sélectionner un produit au hasard
                $product = $this->faker->randomElement($products);
                $ingredient = new Ingredient();
                $ingredient
                    ->setRecipe($recipe)
                    ->setProduct($product)
                    ->setQuantity($this->faker->randomFloat(2, 1, 100))
                    ->setUnit($this->faker->randomElement($units))
                    ->setCreatedAt($recipe->getCreatedAt())
                    ->setUpdatedAt($recipe->getUpdatedAt());

                $this->em->persist($ingredient);
                // Ajouter le produit à la recette
                $recipe->addProduct($product);

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
            CarteFixtures::class,
            ProductFixtures::class,
        ];
    }
}


