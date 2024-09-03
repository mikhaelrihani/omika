<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\media\Mime;
use App\Entity\media\Picture;
use App\Entity\media\Template;
use App\Service\UnsplashApiService;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Class MediaFixtures
 *
 * Fixture class responsible for loading media-related data into the database.
 */
class MediaFixtures extends BaseFixtures implements FixtureGroupInterface
{
    /**
     * @var UnsplashApiService $unsplashApiService Service for fetching images from Unsplash.
     */
    private UnsplashApiService $unsplashApiService;

    /**
     * MediaFixtures constructor.
     *
     * @param UnsplashApiService $unsplashApi The Unsplash API service.
     * @param UserPasswordHasherInterface $userPasswordHasher The password hasher service.
     * @param EntityManagerInterface $em The entity manager interface.
     */
    public function __construct(UnsplashApiService $unsplashApi, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $em)
    {
        parent::__construct($userPasswordHasher, $em);
        $this->unsplashApiService = $unsplashApi;
    }

    public static function getGroups(): array
    {
        return ['group_media'];
    }
    /**
     * Load the media fixtures into the database.
     */
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));

        $mimes = $this->createMimes();
        $this->createTemplates();
        $this->createPictures($mimes);

        $this->em->flush();
    }



    /**
     * Create Mime entities.
     *
     * @return array The array of created Mime entities.
     */
    private function createMimes(): array
    {
        $MimeList = $this->faker->getMimelist();
        $mimes = [];
        foreach ($MimeList as $mimeName) {

            $timestamps = $this->faker->createTimeStamps();

            $mime = new Mime();
            $mime
                ->setName($mimeName)
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);
            $mimes[] = $mime;
            $this->em->persist($mime);
        }
        return $mimes;
    }


    /**
     * Create Template entities.
     */
    private function createTemplates(): void
    {
        for ($t = 0; $t < 10; $t++) {
            // Generate timestamps for each template
            $timestamps = $this->faker->createTimeStamps();

            $template = new Template();
            $template
                ->setName($this->faker->realText(50))
                ->setText($this->faker->realText(1000))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($template);
        }
    }


    /**
     * Create Picture entities.
     *
     * @param array $mimes The array of Mime entities.
     */
    private function createPictures(array $mimes): void
    {
        for ($p = 0; $p < 15; $p++) {
            // Generate timestamps for each picture
            $timestamps = $this->faker->createTimeStamps();

            $randomIndex = array_rand($mimes);
            $mime = $mimes[$randomIndex];
            $picture = new Picture();
            $picture
                ->setName($this->faker->unique()->realText(50))
                ->setSlug($this->faker->unique()->slug)
                ->setMime($mime)
                // Uncomment and use Unsplash API for fetching random food images
                // ->setPath($this->unsplashApiService->fetchPhotosRandom("nourriture saine"))
                ->setPath($this->faker->unique()->imageUrl(640, 480, 'food'))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);
            $this->addReference("picture_{$p}", $picture);

            $this->em->persist($picture);
        }
    }


}
