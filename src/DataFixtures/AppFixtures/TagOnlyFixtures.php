<?php

namespace App\DataFixtures\AppFixtures;


use Doctrine\Persistence\ObjectManager;
use App\DataFixtures\AppFixtures\TagFixtures;
use App\DataFixtures\AppFixtures\BaseFixtures;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;


#[AsTaggedItem("TagOnlyFixtures")]
class TagOnlyFixtures extends BaseFixtures implements DependentFixtureInterface
{

    public function __construct(private TagFixtures $tagFixtures)
    {

    }

    public function load(ObjectManager $manager): void
    {
        // Appel de la fixture TagFixtures uniquement
        $this->tagFixtures->load($manager);
    }

    public function getDependencies(): array
    {
        return [
            EventFixtures::class,
        ];
    }

}
