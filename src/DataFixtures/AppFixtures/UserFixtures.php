<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\user\Absence;
use App\Entity\user\Business;
use App\Entity\user\Contact;
use App\Entity\user\User;
use App\Entity\user\UserLogin;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Uid\Uuid;

class UserFixtures extends BaseFixtures
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
        $surnames = ["rihani"];
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
        $this->setAbsence($user, $surnames, $manager, $createdAt, $updatedAt);
        $manager->persist($userLogin);
        $manager->persist($user);


        for ($u = 0; $u < 10; $u++) {

            // Créer les timestamps
            $createdAt = $this->faker->dateTimeImmutableBetween('-5 years', 'now');
            $updatedAt = $this->faker->dateTimeImmutableBetween($createdAt, 'now');

            //! UserLogin
            $userLogin = new UserLogin();
            $userLogin
                ->setRoles($this->faker->role())
                ->setPassword($this->userPasswordHasher->hashPassword($userLogin, $this->faker->generatePassword()))
                ->setEmail($this->faker->unique()->email())
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
                ->setJob($this->faker->jobTitle())
                ->setPhone($this->faker->generatePhoneNumber())
                ->setWhatsapp($this->faker->generatePhoneNumber())
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            // Associer aleatoirement le contact a un business
            $user->setBusiness($this->faker->business());

            // Associer le UserLogin et l'avatar à l'utilisateur
            $user->setUserLogin($userLogin);

            if (!empty($pictures)) {
                $randomIndex = array_rand($pictures);
                $picture = $pictures[$randomIndex];
                $user->setAvatar($picture);

                // Retirer l'image choisie de l'array pictures pour garantir l'unicité
                array_splice($pictures, $randomIndex, 1);
            }


            //! Absence User
            $this->setAbsence($user, $surnames, $manager, $createdAt, $updatedAt);

            $manager->persist($userLogin);
            $manager->persist($user);
            // Récupérer les surnames de chaque user pour pouvoir associer un author a une absence 
            $surnames[] = $user->getSurname();
            // Ajouter une référence pour pouvoir les récupérer dans d'autres fixtures
            $this->addReference("user_{$u}", $user);

        }
        //! Contact
        for ($c = 0; $c < 10; $c++) {
            $contact = new Contact();
            $contact
                ->setUuid(Uuid::v4())
                ->setFirstname($this->faker->firstName())
                ->setSurname($this->faker->lastName())
                ->setEmail($this->faker->unique()->email())
                ->setPhone($this->faker->generatePhoneNumber())
                ->setWhatsapp($this->faker->generatePhoneNumber())
                ->setJob($this->faker->jobTitle())
                ->setLateCount($this->faker->numberBetween(0, 10))
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            // Associer aleatoirement le contact a un business
            $contact->setBusiness($this->faker->business());

            //! Absence Contact
            $this->setAbsence($contact, $surnames, $manager, $createdAt, $updatedAt);
            $manager->persist($contact);

            // Ajouter une référence pour pouvoir récupérer les contacts dans d'autres fixtures
            $this->addReference("contact_{$c}", $contact);
        }


        //! Business
        $businessList = $this->faker->businessList();

        foreach ($businessList as $businessName) {
            $business = new Business();
            $business
                ->setName($businessName)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            $manager->persist($business);
        }

        $manager->flush();
    }


    public function getDependencies()
    {
        return [
            MediaFixtures::class,
        ];
    }

    public function setAbsence($entity = null, array $surnames = ["rihani"], ObjectManager $manager, $createdAt, $updatedAt)
    {

        $startDate = $this->faker->dateTimeBetween('-1 year', '-1 week');
        $endDate = $this->faker->dateTimeInInterval($startDate, '+' . rand(1, 7) . ' days');
        $randomIndexAbsence = rand(0, 4);

        for ($a = 0; $a < $randomIndexAbsence; $a++) {
            $absence = new Absence();
            // Associer l'absence à l'entité correspondante
            if ($entity instanceof User) {
                $absence->setUser($entity);
            } elseif ($entity instanceof Contact) {
                $absence->setContact($entity);
            }
            $absence
                ->setstartDate($startDate)
                ->setEndDate($endDate)
                ->setStatus($this->faker->status())
                ->setReason($this->faker->sentence())
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt);

            $randomKey = array_rand($surnames);
            $absence->setAuthor($surnames[$randomKey]);

            $manager->persist($absence);
        }
    }
}
