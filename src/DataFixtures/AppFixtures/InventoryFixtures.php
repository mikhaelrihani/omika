<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\Inventory\Inventory;
use App\Entity\Inventory\KitchenSpace;
use App\Entity\Inventory\Room;
use App\Entity\Inventory\RoomProduct;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Class InventoryFixtures
 * Fixture class responsible for loading Inventory-related data into the database.
 * This class creates and persists KitchenSpaces, Rooms, RoomProducts, and Inventories.
 */
class InventoryFixtures extends BaseFixtures implements DependentFixtureInterface
{

    public function load(ObjectManager $manager): void
    {
        $this->createKitchensSpaces();
        $this->createRooms();
        $this->createRoomProducts();
        $this->createInventories(10);
    }

    /**
     *  KitchenSpaces are distinct locations or areas within a kitchen environment.
     */
    public function createKitchensSpaces(): void
    {
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(KitchenSpace::class)->count([]) === 0) {

            $kitchenSpaces = $this->faker->getKitchenSpaceList();
            $timestamps = $this->faker->createTimeStamps();

            foreach ($kitchenSpaces as $space) {
                $kitchenSpace = new KitchenSpace();
                $kitchenSpace->setName($space);
                $kitchenSpace->setCreatedAt($timestamps[ 'createdAt' ]);
                $kitchenSpace->setUpdatedAt($timestamps[ 'updatedAt' ]);

                $this->em->persist($kitchenSpace);
            }
        }

    }

    public function createRooms(): void
    {

        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(Room::class)->count([]) === 0) {

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

                    $this->em->persist($newRoom);
                    $this->addReference("room_{$r}", $newRoom);
                    $r++;
                }
            }
        }
        $this->em->flush();
    }
    /**
     * Associates products with rooms and ensures that every product is assigned 
     * to at least one room, including the shelf location within the room.
     */
    public function createRoomProducts(): void
    {
        $rooms = $this->retrieveEntities("room", $this);
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if (empty($rooms)) {
            $rooms = $this->em->getRepository(Room::class)->findAll();
        }
        $products = $this->retrieveEntities("product", $this);

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
                $room->addRoomProduct($roomProduct);

                $this->em->persist($roomProduct);

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
            }
        }
        $this->em->flush();
    }

    /**
     * This method creates multiple Inventory instances, each associated with a 
     * subset of rooms and products. Inventories track the state of products 
     * in different rooms and are linked to specific users (authors).
     * 
     * It ensures that each product is assigned to at least one inventory, with 
     * a random quantity and location details.
     */
    public function createInventories($numInventory): void
    {
        $timestamps = $this->faker->createTimeStamps();
        $createdAt = $timestamps[ 'createdAt' ];

        $users = $this->retrieveEntities("user", $this);
        $author = $this->faker->randomElement($users);
        $roomList = $this->retrieveEntities("room", $this);
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if (empty($roomList)) {
            $roomList = $this->em->getRepository(Room::class)->findAll();
        }
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if ($this->em->getRepository(Inventory::class)->count() > 0) {
            $numInventory = 5;
        }

        for ($i = 0; $i < $numInventory; $i++) {
            // Modify createdAt and updatedAt timestamps for each inventory
            $createdAt = (clone $createdAt)->modify('+' . $this->faker->numberBetween(0, 7) . ' days');
            $updatedAt = (clone $createdAt)->modify('+' . $this->faker->numberBetween(0, 7) . ' days');

            // Extract year and month from the updatedAt timestamp
            $year = (int) $updatedAt->format('Y');
            $month = (int) $updatedAt->format('m');

            // Randomly select a subset of rooms for this inventory
            $roomCount = rand(1, min(5, count($roomList)));
            $inventoryRooms = $this->faker->randomElements($roomList, $roomCount, false);
            $status = $this->faker->randomElement(['todo', 'done', 'pending', 'late', 'unrealised','todo_modified']);
            $inventory = new Inventory();
            $inventory
                ->setAuthor($author->getFullName())
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($updatedAt)
                ->setStatus( $status)
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
            $this->em->persist($inventory);
        }
        $this->em->flush();
    }

    public function getDependencies()
    {
        return [
            ProductFixtures::class,
            UserFixtures::class,
        ];
    }
}