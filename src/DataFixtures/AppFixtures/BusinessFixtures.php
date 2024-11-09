<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\User\Business;
use Doctrine\Persistence\ObjectManager;

/**
 * Class BusinessFixtures
 *
 * Fixture class responsible for loading Business-related data into the database.
 */
class BusinessFixtures extends BaseFixtures 
{
    /**
     * @var array $businessEntities Array of Business entities created in the fixture.
     */
    private array $businessEntities;


    /**
     * Load the Business fixtures into the database.
     */
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));
        $this->createBusiness();
        $this->em->flush();
        
    }


    /**
     * Create Business entities and store them in $businessEntities.
     */
    public function createBusiness(): void
    {
        $businessList = $this->faker->getBusinessList();
        $this->businessEntities = [];
        $b = 0;
        foreach ($businessList as $businessName) {
            $timestamps = $this->faker->createTimeStamps();
            $business = new Business();
            $business
                ->setName($businessName)
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->businessEntities[] = $business;
            $this->em->persist($business);
            $this->addReference("business_{$b}", $business);
            $b++;
        }
      
    }



}
