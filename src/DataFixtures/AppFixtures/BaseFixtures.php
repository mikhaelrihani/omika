<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\Entity\Carte\Dish;
use App\Entity\Carte\DishCategory;
use App\Entity\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\UnsplashApiService;

//! The database will be captured at the time of loading the fixtures. This capture will then be updated by a cron job.
//! However, the further the loading of fixtures is from the current date, the less relevant the active day range data will be.
//! It is recommended to regularly reload the fixtures or create a cron job to reload them, for example every three days.

//! to load the fixture make the following command :
//! php bin/console cache:clear   && php bin/console doctrine:fixtures:load && php bin/console doctrine:fixtures:load --append 
//! you can load until 5 to 6 times php bin/console doctrine:fixtures:load --append.
//! there is a problem of memory usage that can happens or a freeze while loading event fixture(probably due to a null value), just try to reload

class BaseFixtures extends Fixture implements FixtureInterface
{
    protected \Faker\Generator $faker;

    public function __construct(
        protected UnsplashApiService $unsplashApi,
        protected UserPasswordHasherInterface $userPasswordHasher,
        protected EntityManagerInterface $em,
        
    ) {
        $this->faker = Factory::create("fr_FR");
        $this->faker->addProvider(new AppProvider($this->faker));
    }

   /**
 * Retrieve entities by reference prefix in a fixture.
 *
 * This method fetches all entities with a specified prefix from a fixture's reference repository, 
 * making it easier to handle large sets of related entities without needing each individual reference.
 *
 * @param string $entityName The prefix of the entity references to retrieve.
 * @param object $fixture The fixture object containing the reference repository.
 * @return array The array of retrieved entities matching the prefix.
 */
public function retrieveEntities(string $entityName, object $fixture): ?array
{
    $entities = [];

    foreach ($fixture->referenceRepository->getReferences() as $referenceKey => $entity) {
        if (strpos($referenceKey, "{$entityName}_") === 0) {
            $entities[] = $fixture->getReference($referenceKey);
        }
    }

    return empty($entities) ? null : $entities;
}



    /**
     * Set a picture to an entity from a list of pictures.
     *
     * @param object $entity The entity to set the picture to.
     * @param array $pictures The list of pictures to choose from.
     */
    public function setPicture(object $entity, array &$pictures): void
    {
        if (empty($pictures)) {
            return;
        }
        // Sélectionner une image aléatoire
        $randomIndexPicture = array_rand($pictures);
        $picture = $pictures[$randomIndexPicture];

        // Associer l'image à l'entité selon son type
        if ($entity instanceof User) {
            $entity->setAvatar($picture);
        } elseif ($entity instanceof Dish) {
            $entity->setPicture($picture);
        } elseif ($entity instanceof DishCategory) {
            $entity->setPicture($picture);
        } else {
            throw new \InvalidArgumentException('Entity type not supported for setting a picture.');
        }
        // Retirer l'image choisie par réference (&)du tableau pour garantir l'unicité
        array_splice($pictures, $randomIndexPicture, 1);
    }


    /**
     * Generate a strong password with a mix of uppercase letters, special characters, and numbers.
     *
     * @return string The generated password.
     */
    public static function generatePassword(): string
    {
        $length = 10;
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $specialChars = '!@#$%^&*';
        $numbers = '0123456789';

        // Start building the password with one character from each required category
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $specialChars[random_int(0, strlen($specialChars) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];

        // Fill the rest of the password length with random characters
        $allChars = $uppercase . $specialChars . $numbers . strtolower($uppercase);
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to avoid predictable patterns
        return str_shuffle($password);
    }

    /**
     * Generate a formatted phone number with optional international prefix.
     *
     * @return string The generated phone number.
     */
    public static function generatePhoneNumber(): string
    {
        // Generate a 10-digit phone number
        $number = sprintf('%010d', rand(0, 9999999999));

        // Optionally add a '+' prefix to simulate an international code
        $prefix = rand(0, 1) ? '+' : '';

        // Format the number with spaces every 2 digits
        $formattedNumber = $prefix . implode(' ', str_split($number, 2));

        // Ensure the total length is between 10 and 20 characters
        // If the number is too short, pad it with spaces
        $maxLength = 20;
        $formattedNumber = str_pad($formattedNumber, $maxLength, ' ', STR_PAD_RIGHT);

        // Limit to a maximum length of 20 characters
        return substr($formattedNumber, 0, $maxLength);
    }

    /**
     * @param ObjectManager $manager 
     */
    public function load(ObjectManager $manager): void
    {
        // This method should be overridden in child classes to add fixture logic.
    }
}