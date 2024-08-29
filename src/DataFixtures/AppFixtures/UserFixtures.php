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
use Symfony\Component\Uid\Uuid;

/**
 * Class UserFixtures
 *
 * Fixture class responsible for loading user-related data into the database.
 */
class UserFixtures extends BaseFixtures
{
    /**
     * @var array $businessEntities Array of Business entities created in the fixture.
     */
    private array $businessEntities;

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

    /**
     * Load the user fixtures into the database.
     *
     * @param ObjectManager $manager The ObjectManager instance.
     */
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));
        $this->pictures = $this->retrieveEntities('picture', $this);
        $this->createBusiness();
        $this->createContacts();
        $this->createUsers(10); // You can specify the number of users to create
        $this->em->flush();
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
            ->setCreatedAt($timestamps[ 'createdAt' ])
            ->setUpdatedAt($timestamps[ 'updatedAt' ]);
        $this->em->persist($userLogin);
        return $userLogin;
    }

    /**
     * Create a specified number of User entities.
     *
     * @param int $num The number of User entities to create.
     */
    public function createUsers(int $num): void
    {
        $this->userAdminExists = false;
        for ($u = 0; $u < $num; $u++) {
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
            $user->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            // Assign an avatar to the user
            $this->setPicture($user, $this->pictures);

            // Randomly assign the user to a business
            $randomIndexBusiness = rand(0, count($this->businessEntities) - 1);
            $user->setBusiness($this->businessEntities[$randomIndexBusiness]);

            $this->setAbsenceEntity($user, $this->surnames);
            
            $this->em->persist($user);
            // Add a reference to retrieve users in other fixtures
            $this->addReference("user_{$u}", $user);
        }
    }

    /**
     * Create a number of Contact entities.
     */
    public function createContacts(): void
    {
        for ($c = 0; $c < 10; $c++) {
            $timestamps = $this->faker->createTimeStamps();
            $contact = new Contact();
            $contact
                ->setUuid(Uuid::v4())
                ->setFirstname($this->faker->firstName())
                ->setSurname($this->faker->lastName())
                ->setEmail($this->faker->unique()->email())
                ->setPhone($this->generatePhoneNumber())
                ->setWhatsapp($this->generatePhoneNumber())
                ->setJob($this->faker->jobTitle())
                ->setLateCount($this->faker->numberBetween(0, 10))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            // Randomly assign the contact to a business
            $randomIndexBusiness = rand(0, count($this->businessEntities) - 1);
            $contact->setBusiness($this->businessEntities[$randomIndexBusiness]);

            // Set the absence information for the contact
            $this->setAbsenceEntity($contact, $this->surnames);
            $this->em->persist($contact);

            // Add a reference to retrieve contacts in other fixtures
            $this->addReference("contact_{$c}", $contact);
        }
    }

    /**
     * Create Business entities and store them in $businessEntities.
     */
    public function createBusiness(): void
    {
        $businessList = $this->faker->getBusinessList();
        $this->businessEntities = [];
        foreach ($businessList as $businessName) {
            $timestamps = $this->faker->createTimeStamps();
            $business = new Business();
            $business
                ->setName($businessName)
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->businessEntities[] = $business;
            $this->em->persist($business);
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
                $absence->setUser($entity);
            } elseif ($entity instanceof Contact) {
                $absence->setContact($entity);
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

    /**
     * Get the dependencies for this fixture.
     *
     * @return array The array of fixture classes that this fixture depends on.
     */
    public function getDependencies()
    {
        return [
            MediaFixtures::class,
        ];
    }
}
