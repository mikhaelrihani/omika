<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\CoreFixtures;
use App\Entity\user\User;
use App\Entity\user\UserLogin;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

class UserFixtures extends CoreFixtures
{
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));

        // Créer les timestamps
        $createdAt = $this->faker->dateTimeImmutableBetween('-5 years', 'now');
        $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt, 'now');

        // Récupérer les pictures
        $pictures = [];
        $i = 0;
        while ($this->hasReference("picture_{$i}")) {
            $picture = $this->getReference("picture_{$i}");
            $pictures[] = $picture;
            $i++;
        }

        //! SuperAdmin userLogin
        $userLogin = new UserLogin();
        $userLogin
            ->setRoles(["ROLE_SUPER_ADMIN"])
            ->setPassword($this->userPasswordHasher->hashPassword($userLogin, "Password29!"))
            ->setEmail("mikabernikdev@gmail.com")
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt);

        //! SuperAdmin user 
        $user = new User();
        $user
            ->setUuid(Uuid::v4())
            ->setFirstname("mikhael")
            ->setSurname("rihani")
            ->setPseudo("mika")
            ->setLateCount(0)
            ->setAbsentCount(0)
            ->setJob("headChef")
            ->setPhone("06 92 12 34 56")
            ->setWhatsapp("+33 6 92 12 34 56")
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt);

        // Associer le UserLogin et l'avatar à l'utilisateur
        $user->setUserLogin($userLogin);

        if (!empty($pictures)) {
            $randomIndex = array_rand($pictures);
            $picture = $pictures[$randomIndex];
            $user->setAvatar($picture);

            // Retirer l'image choisie de l'array pictures pour garantir l'unicité
            array_splice($pictures, $randomIndex, 1);
        }

        $manager->persist($userLogin);
        $manager->persist($user);


        for ($k = 0; $k < 10; $k++) {

            // Créer les timestamps
            $createdAt = $this->faker->dateTimeImmutableBetween('-5 years', 'now');
            $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt, 'now');

            //! UserLogin
            $userLogin = new UserLogin();
            $userLogin
                ->setRoles($this->faker->role())
                ->setPassword($this->userPasswordHasher->hashPassword($userLogin, $this->faker->generatePassword()))
                ->setEmail($this->faker->email())
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            //! User
            $user = new User();
            $user
                ->setUuid(Uuid::v4())
                ->setFirstname($this->faker->firstName())
                ->setSurname($this->faker->lastName())
                ->setPseudo($this->faker->userName())
                ->setLateCount($this->faker->numberBetween(0, 10))
                ->setAbsentCount($this->faker->numberBetween(0, 10))
                ->setJob($this->faker->jobTitle())
                ->setPhone($this->faker->generatePhoneNumber())
                ->setWhatsapp($this->faker->generatePhoneNumber())
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            // Associer le UserLogin et l'avatar à l'utilisateur
            $user->setUserLogin($userLogin);

            if (!empty($pictures)) {
                $randomIndex = array_rand($pictures);
                $picture = $pictures[$randomIndex];
                $user->setAvatar($picture);

                // Retirer l'image choisie de l'array pictures pour garantir l'unicité
                array_splice($pictures, $randomIndex, 1);
            }
            $manager->persist($userLogin);
            $manager->persist($user);

            // Ajouter une référence pour pouvoir les récupérer dans d'autres fixtures
            $this->addReference("user_{$k}", $user);
        }

        $manager->flush();
    }


    public function getDependencies()
    {
        return [
            MediaFixtures::class,
        ];
    }
}
