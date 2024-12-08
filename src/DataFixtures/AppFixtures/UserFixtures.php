<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\Media\Message;
use App\Entity\Media\Note;
use App\Entity\User\Absence;
use App\Entity\User\Business;
use App\Entity\User\Contact;
use App\Entity\User\User;
use App\Entity\User\UserLogin;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;



/**
 * Class UserFixtures
 * Fixture class responsible for loading user-related data into the database.
 */
class UserFixtures extends BaseFixtures
{

    private ?array $businessEntities = null;
    /**
     * @var array $pictures Array of pictures retrieved for setting user avatars.
     */
    private array $pictures;

    /**
     * @var array $surnames Array of surnames used for creating Absence entities.
     */
    private array $surnames = [];

    /**
     * @var bool $userAdminExists Flag indicating if a super admin user has already been created.
     */
    private bool $userAdminExists;



    public function load(ObjectManager $manager): void
    {
        //! on fait cette verification pour eviter l'erreur "Notice: Undefined offset: 0" lorsqu'on a pas de business dans la bdd pour "php bin/console doctrine:fixtures:load --append"
        $this->businessEntities = $this->retrieveEntities('business', $this);
        if (empty($businessEntities)) {
            $this->businessEntities = $this->em->getRepository(Business::class)->findAll();
        }
        $this->pictures = $this->retrieveEntities('picture', $this);
        $this->createContacts(10);
        $this->createUsers(5);
        $users = $this->retrieveEntities('user', $this);
        $contacts = $this->retrieveEntities('contact', $this);
     
        $manager->flush();
        //* To avoid a circular dependency between UserFixtures and MediaFixtures,
        //* we implement the logic for creating notes here, rather than in MediaFixtures.
        $this->createNotes($users);

        $manager->flush();
    }

    /**
     * Create a super admin UserLogin entity.
     *
     * @return UserLogin The created UserLogin entity.
     */
    public function createUserLoginAdmin(): UserLogin
    {

        $timestamps = $this->faker->createTimeStamps();
        $userLogin = new UserLogin();
        $userLogin
            ->setRoles(["ROLE_SUPER_ADMIN"])
            ->setPassword($this->userPasswordHasher->hashPassword($userLogin, "Password29!"))
            ->setEmail("mikabernikdev@gmail.com")
            ->setEnabled(true)
            ->setCreatedAt($timestamps[ 'createdAt' ])
            ->setUpdatedAt($timestamps[ 'updatedAt' ]);

        $this->em->persist($userLogin);

        return $userLogin;
    }

    /**
     * Create a regular UserLogin entity with random data.
     *
     * @return UserLogin The created UserLogin entity.
     */
    public function createUserLogin(): UserLogin
    {
        $timestamps = $this->faker->createTimeStamps();
        $userLogin = new UserLogin();
        $userLogin
            ->setRoles($this->faker->role())
            ->setPassword($this->userPasswordHasher->hashPassword($userLogin, "Password29!"))
            ->setEmail($this->faker->unique()->email())
            ->setEnabled($this->faker->boolean(90))
            ->setCreatedAt($timestamps[ 'createdAt' ])
            ->setUpdatedAt($timestamps[ 'updatedAt' ]);
        $this->em->persist($userLogin);
        return $userLogin;
    }

    /**
     * Create a specified number of User entities.
     *
     * @param int $numUsers The number of User entities to create.
     */
    public function createUsers(int $numUsers): void
    {
        //! on fait cette verification depuis la bdd, pour eviter l'unicité de l'entité superadmin afin de pouvoir ajouter plus de fixtures avec "php bin/console doctrine:fixtures:load --append"
        ($this->em->getRepository(User::class)->findBy(["surname" => "rihani"])) ? [$this->userAdminExists = true, $numUsers = 1] : $this->userAdminExists = false;

        for ($u = 0; $u < $numUsers; $u++) {
            $timestamps = $this->faker->createTimeStamps();
            $user = new User();
            if (!$this->userAdminExists) {
                $user
                    ->setUuid(Uuid::v4())
                    ->setFirstname("mikhael")
                    ->setSurname("rihani")
                    ->setPseudo("mika")
                    ->setLateCount(0)
                    ->setJob("headChef")
                    ->setPhone("06 92 12 34 56")
                    ->setWhatsapp("+33 6 92 12 34 56");
                $user->setUserLogin($this->createUserLoginAdmin());
                $this->userAdminExists = true;
            } else {
                $user
                    ->setUuid(Uuid::v4())
                    ->setFirstname($this->faker->firstName())
                    ->setSurname($this->faker->lastName())
                    ->setPseudo($this->faker->userName())
                    ->setLateCount($this->faker->numberBetween(0, 10))
                    ->setJob($this->faker->jobTitle())
                    ->setPhone($this->generatePhoneNumber())
                    ->setWhatsapp($this->generatePhoneNumber());
                $user->setUserLogin($this->createUserLogin());
            }
            $user
                ->setPrivateNote($this->faker->text(200))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            // Assign an avatar to the user
            $this->setPicture($user, $this->pictures);

            // Randomly assign the user to a business


            $randomIndex = rand(0, count($this->businessEntities) - 1);
            $user->setBusiness($this->businessEntities[$randomIndex]);

            // Set the absence information for the user
            $this->setAbsenceEntity($user, $this->surnames);

            $this->em->persist($user);

            // Store the surnames for absence authoring
            $this->surnames[] = $user->getSurname();

            // Add a reference to retrieve users in other fixtures
            $this->addReference("user_{$u}", $user);
        }
    }


    public function createContacts(int $numContacts): void
    {

        //! on fait cette verification depuis la bdd, pour eviter l'unicité de l'entité superadmin afin de pouvoir ajouter plus de fixtures avec "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(Contact::class)->findAll() > 0) {
            $numContacts = 5;
        }
        ;

        for ($c = 0; $c < $numContacts; $c++) {
            $timestamps = $this->faker->createTimeStamps();
            $contact = new Contact();
            $contact
                ->setUuid(Uuid::v4())
                ->setFirstname($this->faker->firstName())
                ->setSurname($this->faker->lastName())
                ->setEmail($this->faker->unique()->email())
                ->setPhone($this->generatePhoneNumber())
                ->setWhatsapp($this->generatePhoneNumber());
            if ($c < 4) {
                $contact->setJob("technicien");
            } else {
                $contact->setJob($this->faker->jobTitle());
            }
            ;
            $contact
                ->setLateCount($this->faker->numberBetween(0, 10))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            // Randomly assign the contact to a business
            $randomIndex = rand(0, count($this->businessEntities) - 1);
            $contact->setBusiness($this->businessEntities[$randomIndex]);

            // Set the absence information for the contact
            $this->setAbsenceEntity($contact, $this->surnames);
            $this->em->persist($contact);

            // Store the surnames for absence authoring
            $this->surnames[] = $contact->getSurname();

            $this->addReference("contact_{$c}", $contact);
        }
    }



    /**
     * Set the absence information for a given entity (User or Contact).
     *
     * @param User|Contact|null $entity The entity for which to set absence data.
     * @param array|null $surnames Optional array of surnames to use for the absence author.
     */
    public function setAbsenceEntity($entity = null, array $surnames = null)
    {
        if (empty($surnames)) {
            $surnames = ["rihani"];
        }
        $timestamps = $this->faker->createTimeStamps();
        $startDate = $this->faker->dateTimeBetween('-1 year', '-1 week');
        $endDate = $this->faker->dateTimeInInterval($startDate, '+' . rand(1, 7) . ' days');
        $randomIndexAbsence = rand(0, 4);

        for ($a = 0; $a < $randomIndexAbsence; $a++) {

            $absence = new Absence();
            // Associate the absence with the correct entity
            if ($entity instanceof User) {
                $entity->addAbsence($absence);
            } elseif ($entity instanceof Contact) {
                $entity->addAbsence($absence);
            }
            $absence
                ->setstartDate($startDate)
                ->setEndDate($endDate)
                ->setPlanningUpdate($this->faker->boolean())
                ->setStatus($this->faker->getOneRandomStatus())
                ->setReason($this->faker->sentence())
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $randomKey = array_rand($surnames);
            $absence->setAuthor($surnames[$randomKey]);

            $this->em->persist($absence);

        }
    }


    private function createNotes(array $users): void
    {
        foreach ($users as $user) {


            $timestamps = $this->faker->createTimeStamps();

            $note = new Note();
            $note
                ->setAuthor($user)
                ->setText($this->faker->text(200))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($note);
        }
    }

   

    public function getDependencies()
    {
        return [
            MediaFixtures::class,
            BusinessFixtures::class,
        ];
    }
}