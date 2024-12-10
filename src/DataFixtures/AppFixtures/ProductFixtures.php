<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\Order\Order;
use App\Entity\Order\ProductOrder;
use App\Entity\Product\Product;
use App\Entity\Product\ProductType;
use App\Entity\Product\Rupture;
use App\Entity\Supplier\DeliveryDay;
use App\Entity\Supplier\Supplier;
use App\Entity\Recipe\Unit;
use App\Entity\Supplier\OrderDay;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;


/**
 * Class ProductFixtures
 *
 * Fixture class responsible for loading product-related data into the database.
 * This class creates and persists Units, ProductTypes, Suppliers, Products, Ruptures, and Orders,
 * and manages the relationships between Products and Orders.
 */
class ProductFixtures extends BaseFixtures implements DependentFixtureInterface
{
    private $numProduct = 200;
    private array $users;

    /**
     * Load the product fixtures into the database.
     *
     * This method is responsible for orchestrating the creation and persistence of product-related
     * data, including units, product types, suppliers, products, ruptures, orders, and their relationships.
     * 
     * @param ObjectManager $manager 
     */
    public function load(ObjectManager $manager): void
    {

        $this->users = $this->retrieveEntities('user', $this);

        $this->createUnits();
        $this->createProductTypes();
        $this->createSuppliers();
        $this->createProducts($this->numProduct);
        $this->createRuptures();
        $this->createOrders(20, );
        $this->products_Orders();

    }

    /**
     * Generates a list of units with names and symbols.
     */
    private function createUnits(): void
    {
        //! on fait cette verification pour "php bin/console doctrine:fixtures:load --append"
        $units = $this->em->getRepository(Unit::class)->findAll();
        if (empty($units)) {
            $unitNames = $this->faker->getUnitList();
            $u = 0;

            foreach ($unitNames as $unitName => $unitSymbol) {
                $timestamps = $this->faker->createTimeStamps();

                $unit = new Unit();
                $unit
                    ->setName($unitName)
                    ->setSymbol($unitSymbol)
                    ->setCreatedAt($timestamps[ 'createdAt' ])
                    ->setUpdatedAt($timestamps[ 'updatedAt' ]);

                $this->em->persist($unit);
                $this->addReference("unit_{$u}", $unit);
                $u++;
            }

        }
    }

    /**
     * Create ProductTypes and persist them to the database.
     *
     * Generates a list of product types and saves them into the database.
     * Each product type is initialized with creation and update timestamps.
     */
    private function createProductTypes(): void
    {
        $productTypes = $this->faker->getProductTypeList();
        //! on fait cette verification pour "php bin/console doctrine:fixtures:load --append"
        if (empty($productTypes)) {
            $productTypes = $this->em->getRepository(ProductType::class)->findAll();
        }
        $t = 0;

        foreach ($productTypes as $type) {
            $timestamps = $this->faker->createTimeStamps();

            $productType = new ProductType();
            $productType
                ->setName($type)
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($productType);
            $this->addReference("productType_{$t}", $productType);
            $t++;
        }
    }

    /**
     * Create Suppliers and persist them to the database.
     *
     * Generates a list of suppliers associated with businesses and saves them into the database.
     * Each supplier includes logistical details, ordering habits, and delivery information.
     */
    private function createSuppliers(): void
    {
        //! on fait cette verification pour conserver l unicite de la relation en one to one supplier/business pour "php bin/console doctrine:fixtures:load --append"
        $businesses = $this->retrieveEntities('business', $this);
        if (!empty($businesses)) {
            $s = 0;

            foreach ($businesses as $business) {
                $timestamps = $this->faker->createTimeStamps();

                $days = [1, 2, 3, 4, 5, 6, 7];

                // Récupérer des indices de jours de commande aléatoires
                $orderDaysEntries = (array) array_rand($days, rand(1, 3));
                $orderDays = [];

                // Récupérer les jours de commande à partir des indices
                foreach ($orderDaysEntries as $index) {
                    $orderDays[] = $days[$index];
                }

                // Jours disponibles pour la livraison
                $deliveryAvailableDays = array_diff($days, $orderDays);

                // Calculer le nombre de jours de livraison, choisir le même nombre que de jours de commande
                $deliveryDaysCount = count($orderDays);

                // Vérifier si des jours de livraison sont disponibles 
                if (count($deliveryAvailableDays) < $deliveryDaysCount) {
                    $deliveryDaysCount = count($deliveryAvailableDays);
                }

                // Choisir des jours de livraison parmi les jours disponibles
                $deliveryDaysEntries = (array) array_rand($deliveryAvailableDays, $deliveryDaysCount);
                $deliveryDays = [];

                // Récupérer les jours de livraison à partir des indices
                foreach ($deliveryDaysEntries as $index) {
                    $deliveryDays[] = $deliveryAvailableDays[$index];
                }

                $supplier = new Supplier();
                $supplier
                    ->setBusiness($business)
                    ->setLogistic($this->faker->text(50))
                    ->setHabits($this->faker->text(50))
                    ->setGoodToKnow($this->faker->text(50))
                    ->setCreatedAt($timestamps[ 'createdAt' ])
                    ->setUpdatedAt($timestamps[ 'updatedAt' ]);

                // Ajouter les jours de commande
                foreach ($orderDays as $day) {
                    $orderDay = new OrderDay();
                    $orderDay->setDay($day);
                    $supplier->addOrderDay($orderDay);
                }

                // Ajouter les jours de livraison
                foreach ($deliveryDays as $day) {
                    $deliveryDay = new DeliveryDay();
                    $deliveryDay->setDay($day);
                    $supplier->addDeliveryDay($deliveryDay);
                }

                $this->em->persist($supplier);
                $this->addReference("supplier_{$s}", $supplier);
                $s++;
            }
        }
    }

    /**
     * Ensures that products with the same kitchen name share the same product type and at least one product is marked as a supplier favorite.
     *
     * @param int $numProduct The number of products to create.
     */
    private function createProducts(int $numProduct): void
    {
        $units = $this->retrieveEntities('unit', $this);
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if (empty($units)) {
            $units = $this->em->getRepository(unit::class)->findAll();
        }
        $productTypes = $this->retrieveEntities('productType', $this);
        //! on fait cette verification pour "php bin/console doctrine:fixtures:load --append"
        if (empty($productTypes)) {
            $productTypes = $this->em->getRepository(ProductType::class)->findAll();
        }
        $suppliers = $this->retrieveEntities('supplier', $this);
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if (empty($suppliers)) {
            $suppliers = $this->em->getRepository(Supplier::class)->findAll();
        }
        $favoriteAssigned = []; // Track favorite products for each kitchen name
        $kitchenName = []; // Map kitchen names to product types

        // Pre-create a few kitchen names with product types
        for ($i = 0; $i < floor($numProduct / 3); $i++) {
            $name = $this->faker->unique->text(10); // Unique kitchen name
            $type = $this->faker->randomElement($productTypes);
            $kitchenName[$name] = $type;
        }

        for ($p = 0; $p < $numProduct; $p++) {
            $timestamps = $this->faker->createTimeStamps();

            $product = new Product();
            $product
                ->setUnit($this->faker->randomElement($units))
                ->setSupplier($this->faker->randomElement($suppliers));

            // Set product type and kitchen name
            $productKitchenName = $this->faker->randomElement(array_keys($kitchenName));
            $product->setType($kitchenName[$productKitchenName]);
            $product->setKitchenName($productKitchenName);

            // Ensure at least one product per kitchen name is marked as a supplier favorite
            if (!isset($favoriteAssigned[$productKitchenName])) {
                $product->setSupplierFavorite(true);
                $favoriteAssigned[$productKitchenName] = true;
            } else {
                $product->setSupplierFavorite(false);
            }

            $product
                ->setCommercialName($this->faker->unique->text(50))
                ->setSlug($this->faker->unique->slug)
                ->setPrice($this->faker->randomFloat(2, 0.01, 100))
                ->setConditionning($this->faker->text(50))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($product);
            $this->addReference("product_{$p}", $product);
        }
    }

    /**
     * Generates a list of ruptures (disruptions) with associated products and persists them to the database.
     * Each rupture is assigned to a unique product.
     */
    private function createRuptures(): void
    {
        $products = $this->retrieveEntities('product', $this);
        $numRuptures = floor($this->numProduct / 7);

        for ($r = 0; $r < $numRuptures; $r++) {
            $timestamps = $this->faker->createTimeStamps();

            $rupture = new Rupture();
            $rupture
                ->setInfo($this->faker->text(50))
                ->setOrigin($this->faker->text(50))
                ->setUniqueSolution($this->faker->text(50))
                ->setSolution($this->faker->text(50))
                ->setStatus($this->faker->randomElement(['active', 'inactive']))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            // Assign a unique product to the rupture
            if (!empty($products)) {
                $randomIndexProduct = array_rand($products);
                $product = $products[$randomIndexProduct];
                $rupture->setProduct($product);
                array_splice($products, $randomIndexProduct, 1); // Remove the assigned product from the list
            }

            $this->em->persist($rupture);
            $this->addReference("rupture_{$r}", $rupture);
        }
    }

    /**
     * @param int $numOrder The number of orders to create.
     */
    private function createOrders(int $numOrder): void
    {
        $suppliers = $this->retrieveEntities('supplier', $this);
        //! on fait cette verification  pour "php bin/console doctrine:fixtures:load --append"
        if (empty($suppliers)) {
            $suppliers = $this->em->getRepository(Supplier::class)->findAll();
        }
        for ($o = 0; $o < $numOrder; $o++) {
            $timestamps = $this->faker->createTimeStamps();
            $orderDate = $timestamps[ 'updatedAt' ];
            $daysToAdd = rand(1, 5);
            $deliveryDate = $orderDate->modify("+{$daysToAdd} days");
            $author = $this->faker->randomElement($this->users);

            $order = new Order();
            $order
                ->setSupplier($this->faker->randomElement($suppliers))
                ->setAuthor($author->getFullName())
                ->setStatus($this->faker->randomElement(['pending', 'sent']))
                ->setSendingMethod($this->faker->randomElement(['phone', 'email']))
                ->setNote($this->faker->text(50))
                ->setPdfPath($this->faker->unique->url . '.pdf')
                ->setDeliveryDate($deliveryDate)
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($order);
            $this->addReference("order_{$o}", $order);
        }
    }

    /**
     * Creates associations between products and orders, ensuring that each order contains a set of products from the same supplier.
     * Each product is assigned a quantity and associated with an order.
     */
    private function products_Orders(): void
    {
        $products = $this->retrieveEntities('product', $this);
        $orders = $this->retrieveEntities('order', $this);

        foreach ($orders as $order) {
            $timestamps = $this->faker->createTimeStamps();
            $supplier = $order->getSupplier();
            $supplierProducts = [];

            // Filter products to include only those from the supplier of the current order
            foreach ($products as $product) {
                if ($supplier === $product->getSupplier()) {
                    $supplierProducts[] = $product;
                }
            }

            // Create product-order associations
            $numProductByOrder = floor(count($supplierProducts) / 3); // Determine the number of products per order
            for ($i = 0; $i < $numProductByOrder; $i++) {
                // Select a random product from the filtered list
                $randomIndexProduct = array_rand($supplierProducts);
                $product = $supplierProducts[$randomIndexProduct];

                $product_Order = new ProductOrder();
                $product_Order
                    ->setProduct($product)
                    ->setOrder($order)
                    ->setQuantity($this->faker->numberBetween(1, 50))
                    ->setCreatedAt($timestamps[ 'createdAt' ])
                    ->setUpdatedAt($timestamps[ 'updatedAt' ]);

                $this->em->persist($product_Order);

                // Remove the product from the list to avoid duplication
                array_splice($supplierProducts, $randomIndexProduct, 1);
            }
        }
        $this->em->flush();
    }

    /**
     * Get the dependencies for this fixture.
     *
     * Specifies the fixture classes that this fixture depends on. This ensures that dependent fixtures
     * (such as UserFixtures and BusinessFixtures) are loaded before this fixture.
     *
     * @return array The array of fixture classes that this fixture depends on.
     */
    public function getDependencies()
    {
        return [
            UserFixtures::class,
            BusinessFixtures::class,
        ];
    }
}