<?php

namespace App\DataFixtures\AppFixtures;

use App\Entity\Event\Section;
use Doctrine\Persistence\ObjectManager;

class SectionsFixtures extends BaseFixtures
{
    public function load(ObjectManager $manager): void
    {
        $this->createSections();
    }

    public function createSections(): void
    {
        $timestamps = $this->faker->createTimeStamps();
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(Section::class)->count([]) === 0) {

            $Sections = $this->faker->getSectionList();
            $s = 0;
            foreach ($Sections as $section) {
                $Section = new Section();
                $Section->setName($section);
                $Section->setCreatedAt($timestamps[ 'createdAt' ]);
                $Section->setUpdatedAt($timestamps[ 'updatedAt' ]);
                $this->em->persist($Section);
                $this->addReference("section_{$s}", $Section);
                $s++;
            }
        }
        $this->em->flush();
    }
}
