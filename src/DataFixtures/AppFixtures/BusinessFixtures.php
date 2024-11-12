<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\User\Business;
use Doctrine\Persistence\ObjectManager;

/**
 * Class BusinessFixtures
 * Fixture class responsible for loading Business-related data into the database.
 */
class BusinessFixtures extends BaseFixtures
{
    public function load(ObjectManager $manager): void
    {
        $this->createBusiness();
    }

    /**
     * Create Business entities and store them in $businessEntities.
     */
    public function createBusiness(): void
    {
        $businessList = $this->faker->getBusinessList();
        $b = 0;
        foreach ($businessList as $businessName) {
            $timestamps = $this->faker->createTimeStamps();
            $business = new Business();
            $business
                ->setName($businessName)
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);
            if ($this->em->getRepository(Business::class)->findOneBy(['name' => $businessName]) === null) {
                $this->em->persist($business);
                $this->em->flush();
                $this->addReference("business_{$b}", $business);
            }
            $b++;
        }
    }
}
