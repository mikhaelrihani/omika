<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\inventory\Inventory;
use App\Entity\inventory\KitchenSpace;
use App\Entity\inventory\ProductInventory;
use App\Entity\inventory\Room;
use App\Entity\inventory\RoomProduct;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Class InventoryFixtures
 *
 * Fixture class responsible for loading Inventory-related data into the database.
 * This class creates and persists KitchenSpaces, Rooms, RoomProducts, and Inventories.
 */
class InventoryFixtures extends BaseFixtures implements DependentFixtureInterface
{
    /**
     * Load the Inventory fixtures into the database.
     *
     * This method orchestrates the creation of KitchenSpaces, Rooms, RoomProducts, and Inventories,
     * and saves them into the database. It ensures that all required fixtures are created in the
     * correct order and that entities are persisted and flushed properly.
     * 
     * @param ObjectManager $manager The Doctrine ObjectManager instance.
     */
    public function load(ObjectManager $manager): void
    {
        // Add custom data provider for generating fake data
        $this->faker->addProvider(new AppProvider($this->faker));

        // Create KitchenSpaces and persist them to the database
        $this->createKitchensSpaces();

        // Create Rooms and persist them to the database
        $this->createRooms();
        $manager->flush();

        // Create RoomProducts and persist them to the database
        $this->createRoomProducts();
        $manager->flush();

        // Create Inventories and persist them to the database
        $this->createInventories();
        $manager->flush();
    }

    /**
     * Create KitchenSpaces and persist them to the database.
     *
     * Generates a list of KitchenSpaces using a faker provider and saves them
     * into the database. KitchenSpaces are distinct locations or areas within 
     * a kitchen environment.
     */
    public function createKitchensSpaces(): void
    {
        $kitchenSpaces = $this->faker->getKitchenSpaceList();
        $timestamps = $this->faker->createTimeStamps();
        $k = 0;

        foreach ($kitchenSpaces as $space) {
            $kitchenSpace = new KitchenSpace();
            $kitchenSpace->setName($space);
            $kitchenSpace->setCreatedAt($timestamps[ 'createdAt' ]);
            $kitchenSpace->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($kitchenSpace);
            $this->addReference("kitchenSpace_{$k}", $kitchenSpace);
            $k++;
        }
    }

    /**
     * Create Rooms and persist them to the database.
     *
     * Generates a list of Rooms using a faker provider, assigns them details,
     * and saves them into the database. Rooms represent different storage areas 
     * within a kitchen .
     */
    public function createRooms(): void
    {
        $r = 0;
        $rooms = $this->faker->getRoomList();
        $timestamps = $this->faker->createTimeStamps();

        foreach ($rooms as $room) {

            $numRooms = $this->faker->numberBetween(1, 5);
            for ($i = 0; $i < $numRooms; $i++) {
                $newRoom = new Room();
                $newRoom->setName($room);
                $newRoom->setLocationDetails($this->faker->text(100));
                $newRoom->setCreatedAt($timestamps[ 'createdAt' ]);
                $newRoom->setUpdatedAt($timestamps[ 'updatedAt' ]);

                $this->em->persist($room);
                $this->addReference("room_{$r}", $room);
                $r++;
            }
        }
    }

    /**
     * Create Inventories and persist them to the database.
     *
     * This method creates multiple Inventory instances, each associated with a 
     * subset of rooms and products. Inventories track the state of products 
     * in different rooms and are linked to specific users (authors).
     * 
     * It ensures that each product is assigned to at least one inventory, with 
     * a random quantity and location details.
     */
    public function createInventories(): void
    {
        // Get the current time stamps for creating and updating records
        $timestamps = $this->faker->createTimeStamps();
        $createdAt = $timestamps[ 'createdAt' ];

        $users = $this->retrieveEntities("user", $this);
        $author = $this->faker->randomElement($users);
        $roomList = $this->retrieveEntities("room", $this);

        // Create multiple inventories
        for ($i = 0; $i < 30; $i++) {
            // Modify createdAt and updatedAt timestamps for each inventory
            $createdAt = (clone $createdAt)->modify('+' . $this->faker->numberBetween(0, 7) . ' days');
            $updatedAt = (clone $createdAt)->modify('+' . $this->faker->numberBetween(0, 7) . ' days');

            // Extract year and month from the updatedAt timestamp
            $year = (int) $updatedAt->format('Y');
            $month = (int) $updatedAt->format('m');

            // Randomly select a subset of rooms for this inventory
            $roomCount = rand(1, min(5, count($roomList)));
            $inventoryRooms = $this->faker->randomElements($roomList, $roomCount, false);

            // Create a new Inventory instance
            $inventory = new Inventory();
            $inventory
                ->setAuthor($author->getFullName())
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setStatus($this->faker->getOneRandomStatus())
                ->setType($this->faker->randomElement(['partial', 'total']))
                ->setYear($year)
                ->setMonth($month)
                ->setExcelPath($this->faker->unique()->url())
                ->setPdfPath($this->faker->unique()->url());

            // Add rooms and their products to the inventory
            foreach ($inventoryRooms as $room) {
                $products = $room->getProducts();
                $inventory->addRoom($room);
                foreach ($products as $product) {
                    $inventory->addProductInventory($product, $this->faker->numberBetween(1, 10), $this->faker->numberBetween(1, 10));
                }
            }

            // Persist the Inventory object and add reference for later use
            $this->em->persist($inventory);
            $this->addReference("inventory_{$i}", $inventory);
        }
    }

    /**
     * Create RoomProducts and persist them to the database.
     *
     * Associates products with rooms and ensures that every product is assigned 
     * to at least one room. This method creates RoomProduct instances, which 
     * represent the relationship between rooms and products, including details 
     * such as the shelf location within the room.
     */
    public function createRoomProducts(): void
    {
        $rooms = $this->retrieveEntities("room", $this);
        $products = $this->retrieveEntities("product", $this);
        $r = 0;

        // Create a list to track which products have been assigned
        $productAssignments = [];

        // Assign products to rooms
        foreach ($rooms as $room) {
            $numProducts = $this->faker->numberBetween(5, 20);
            $availableProducts = $products;
            $assignedProducts = [];

            for ($p = 0; $p < $numProducts; $p++) {
                // Ensure the product is not already assigned to the room
                $randomIndexProduct = array_rand($availableProducts);
                $product = $availableProducts[$randomIndexProduct];

                // Create a new RoomProduct instance
                $roomProduct = new RoomProduct();
                $roomProduct
                    ->setRoom($room)
                    ->setProduct($product)
                    ->setRoomShelf(rand(1, 10))
                    ->setCreatedAt($room->getCreatedAt())
                    ->setUpdatedAt($room->getUpdatedAt());

                // Add the relationship to the room
                $room->addRoomProduct($roomProduct);

                // Persist the RoomProduct object
                $this->em->persist($roomProduct);
                $this->addReference("roomProduct_{$r}", $roomProduct);
                $r++;

                // Record the product assignment
                $assignedProducts[] = $product;
                array_splice($availableProducts, $randomIndexProduct, 1);
            }

            // Update the list of assigned products
            $productAssignments = array_merge($productAssignments, $assignedProducts);
        }

        // Assign remaining products to random rooms
        foreach ($products as $product) {
            if (!in_array($product, $productAssignments)) {
                // Select a random room to assign the remaining product
                $randomRoom = $this->faker->randomElement($rooms);
                $roomProduct = new RoomProduct();
                $roomProduct
                    ->setRoom($randomRoom)
                    ->setProduct($product)
                    ->setRoomShelf(rand(1, 10))
                    ->setCreatedAt($randomRoom->getCreatedAt())
                    ->setUpdatedAt($randomRoom->getUpdatedAt());

                $randomRoom->addRoomProduct($roomProduct);

                $this->em->persist($roomProduct);
                $this->addReference("roomProduct_{$r}", $roomProduct);
                $r++;
            }
        }

        // Flush the changes to the database
        $this->em->flush();
    }

    /**
     * Get the dependencies for this fixture.
     *
     * Specifies the fixture classes that this fixture depends on. This ensures
     * that dependent fixtures (such as ProductFixtures and UserFixtures) are
     * loaded before this fixture.
     *
     * @return array The array of fixture classes that this fixture depends on.
     */
    public function getDependencies()
    {
        return [
            ProductFixtures::class,
            UserFixtures::class,
        ];
    }
}
