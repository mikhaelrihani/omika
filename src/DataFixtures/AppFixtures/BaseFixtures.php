<?php

namespace App\DataFixtures\AppFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BaseFixtures extends Fixture 
{
    protected $userPasswordHasher;
    protected $faker;
    protected $createdAt; 

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
        $this->faker = Factory::create("fr_FR");
        $this->createdAt = $this->faker->dateTimeBetween('-5 years', 'now');
    }
    public function load(ObjectManager $manager): void
    {
        
    }
}
