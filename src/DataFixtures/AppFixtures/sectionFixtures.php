<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\Entity\Event\Section;
use Doctrine\Persistence\ObjectManager;

class SectionFixtures extends BaseFixtures
{
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));
        // Créer les sections d'événements
        $this->createSections();
        $this->em->flush();

    }
    /**
     * Crée des sections et les enregistre dans la base de données.
     *
     * Cette méthode génère plusieurs sections, chaque section ayant un nom défini dans la liste
     * retournée par `getSectionList`. Chaque section reçoit également des timestamps de création et
     * de mise à jour aléatoires. Un identifiant de référence unique est ajouté pour chaque section
     * pour une utilisation ultérieure.
     *
     * @return void
     */
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


    }
}
