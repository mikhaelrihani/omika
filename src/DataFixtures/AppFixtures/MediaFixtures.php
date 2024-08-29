<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\media\Message;
use App\Entity\media\Mime;
use App\Entity\media\Note;
use App\Entity\media\Picture;
use App\Entity\media\Template;
use App\Service\UnsplashApiService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Class MediaFixtures
 *
 * Fixture class responsible for loading media-related data into the database.
 */
class MediaFixtures extends BaseFixtures
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

    /**
     * Load the media fixtures into the database.
     */
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));

        // Retrieve user and contact references using the BaseFixtures method
        $users = $this->retrieveEntities('user', $this);
        $contacts = $this->retrieveEntities('contact', $this);

        // Combine users and contacts for message creation
        $everybody = array_merge($users, $contacts);

        // Create Message entities
        $this->createMessages($everybody);

        // Create Mime entities
        $mimes = $this->createMimes();

        // Create Template entities
        $this->createTemplates();

        // Create Note entities
        $this->createNotes($users);

        // Create Picture entities
        $this->createPictures($mimes);

        // Flush all persisted entities
        $this->em->flush();
    }

    /**
     * Create Message entities.
     *
     * @param array $everybody The array of users and contacts for message creation.
     */
    private function createMessages(array $everybody): void
    {
        for ($m = 0; $m < 30; $m++) {
            if (!empty($everybody)) {
               
                $timestamps = $this->faker->createTimeStamps();

                $message = new Message();
                $randomIndex = array_rand($everybody);
                $writer = $everybody[$randomIndex];
                array_splice($everybody, $randomIndex, 1);

                if (!empty($everybody)) {
                    $recipient = $this->faker->randomElement($everybody);
                    $message
                        ->setWriter($writer)
                        ->setRecipient($recipient)
                        ->setText($this->faker->realText(1000))
                        ->setCreatedAt($timestamps['createdAt'])
                        ->setUpdatedAt($timestamps['updatedAt']);

                    $this->em->persist($message);
                }
                $everybody[] = $writer;
            }
        }
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
                ->setCreatedAt($timestamps['createdAt'])
                ->setUpdatedAt($timestamps['updatedAt']);
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
                ->setCreatedAt($timestamps['createdAt'])
                ->setUpdatedAt($timestamps['updatedAt']);

            $this->em->persist($template);
        }
    }

    /**
     * Create Note entities.
     *
     * @param array $users The array of User entities.
     */
    private function createNotes(array $users): void
    {
        foreach ($users as $user) {
        
            $timestamps = $this->faker->createTimeStamps();

            $note = new Note();
            $note
                ->setUser($user)
                ->setText($this->faker->realText(1000))
                ->setCreatedAt($timestamps['createdAt'])
                ->setUpdatedAt($timestamps['updatedAt']);

            $this->em->persist($note);
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
                ->setCreatedAt($timestamps['createdAt'])
                ->setUpdatedAt($timestamps['updatedAt']);

            $this->em->persist($picture);
        }
    }

    /**
     * Get the dependencies for this fixture.
     *
     * @return array The array of fixture classes that this fixture depends on.
     */
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
