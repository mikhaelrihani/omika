<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\Media\Mime;
use App\Entity\Media\Picture;
use App\Entity\Media\Template;
use Doctrine\Persistence\ObjectManager;


class MediaFixtures extends BaseFixtures
{
    //! for now we dont use unsplash api due to limitation of 50 pics of the free plan

    public function load(ObjectManager $manager): void
    {
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

        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(Mime::class)->count([]) > 0) {
            $mimes = $this->em->getRepository(Mime::class)->findAll();
        } else {
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
        for ($p = 0; $p < 80; $p++) {
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
