<?php

namespace App\DataFixtures\AppFixtures;

use App\DataFixtures\Provider\AppProvider;
use App\DataFixtures\AppFixtures\BaseFixtures;
use App\Entity\order\Order;
use App\Entity\order\ProductOrder;
use App\Entity\product\Product;
use App\Entity\product\ProductType;
use App\Entity\product\Rupture;
use App\Entity\product\Supplier;
use App\Entity\recipe\Unit;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Class RecipeFixtures
 *
 * Fixture class responsible for loading recipe-related data into the database.
 */
class RecipeFixtures extends BaseFixtures implements DependentFixtureInterface
{
    private $numProduct = 100;
    private array $users;
    /**
     * Load the product fixtures into the database.
     */
    public function load(ObjectManager $manager): void
    {
        $this->faker->addProvider(new AppProvider($this->faker));
        $this->users = $this->retrieveEntities('user', $this);
     
     

        $this->em->flush();
    }
   

    private function createUnits(): void
    {
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

    private function createProductTypes(): void
    {
        $productTypes = $this->faker->getProductTypeList();
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

    private function createSuppliers(): void
    {
        $businesses = $this->retrieveEntities('business', $this);
        $s = 0;
        foreach ($businesses as $business) {

            $timestamps = $this->faker->createTimeStamps();
            $numOrderDays = $this->faker->numberBetween(1, 7);
            $numDeliveryDays = $this->faker->numberBetween(1, 7);
            $supplier = new Supplier();
            $supplier
                ->setBusiness($business)
                ->setLogistic($this->faker->text(50))
                ->setHabits($this->faker->text(50))
                ->setOrderDays($this->faker->randomElements(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], $numOrderDays))
                ->setDeliveryDays($this->faker->randomElements(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], $numDeliveryDays))
                ->setGoodToKnow($this->faker->text(50))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($supplier);
            $this->addReference("supplier_{$s}", $supplier);
            $s++;
        }

    }

    private function createProducts($numProduct): void
    {

        $units = $this->retrieveEntities('unit', $this);
        $productTypes = $this->retrieveEntities('productType', $this);
        $suppliers = $this->retrieveEntities('supplier', $this);
        $favoriteAssigned = [];
        $kitchenName = [];

        for ($i = 0; $i < floor($numProduct / 3); $i++) {
            $name = $this->faker->unique->text(10);
            $type = $this->faker->randomElement($productTypes);
            $kitchenName[$name] = $type;
        }

        for ($p = 0; $p < $numProduct; $p++) {

            $timestamps = $this->faker->createTimeStamps();
            $product = new Product();
            $product
                ->setUnit($this->faker->randomElement($units))
                ->setSupplier($this->faker->randomElement($suppliers));

            // A product can have a same kitchenName than other products but they all have to get the same type
            $productKitchenName = $this->faker->randomElement(array_keys($kitchenName));
            $product->setProductType($kitchenName[$productKitchenName]);
            $product->setKitchenName($productKitchenName);

            // we check if the product is a supplier favorite, if not we set the value to true 
            //to ensure that among the product with the similar kitchenName we have at least one supplier favorite
            if (!isset($favoriteAssigned[$productKitchenName])) {
                $product->setSupplierFavorite(true);
                $favoriteAssigned[$productKitchenName] = true; // Marque ce produit comme favori pour ce nom de cuisine
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
        ;
    }

    private function createRuptures(): void
    {
        $products = $this->retrieveEntities('product', $this);
        $numRuptures = floor($this->numProduct / 7);
        for ($r = 0; $r < $numRuptures; $r++) {

            $timestamps = $this->faker->createTimeStamps();
            $rupture = new Rupture();
            $rupture->setInfo($this->faker->text(50))
                ->setOrigin($this->faker->text(50))
                ->setUniqueSolution($this->faker->text(50))
                ->setSolution($this->faker->text(50))
                ->setStatus($this->faker->randomElement(['pending', 'solved']))
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            // We add a unique product to the rupture, all ruptures need to be assigned to a product
            if (!empty($products)) {
                $randomIndexProduct = array_rand($products);
                $product = $products[$randomIndexProduct];
                $rupture->setProduct($product);
                array_splice($products, $randomIndexProduct, 1);
            }

            $this->em->persist($rupture);
            $this->addReference("rupture_{$r}", $rupture);

        }
    }

    private function createOrders($numOrder): void
    {

        $suppliers = $this->retrieveEntities('supplier', $this);


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
                ->setStatus($this->faker->randomElement(['draft', 'sent']))
                ->setSendingMethod($this->faker->randomElement(['phone', 'email']))
                ->setNote($this->faker->text(50))
                ->setPdfPath($this->faker->unique->url . '.pdf')
                ->setDeliveryDate( $deliveryDate)
                ->setCreatedAt($timestamps[ 'createdAt' ])
                ->setUpdatedAt($timestamps[ 'updatedAt' ]);

            $this->em->persist($order);
            $this->addReference("order_{$o}", $order);

        }

    }
    private function products_Orders(): void
    {
        $products = $this->retrieveEntities('product', $this);
        $orders = $this->retrieveEntities('order', $this);

        foreach ($orders as $order) { {

                $timestamps = $this->faker->createTimeStamps();
                $supplier = $order->getSupplier();
                $supplierProducts = [];

                // Filtrer les produits pour ne garder que ceux du fournisseur sélectionné
                foreach ($products as $product) {
                    if ($supplier === $product->getSupplier()) {
                        $supplierProducts[] = $product;
                    }
                }

                $numProductByOrder = floor(count($supplierProducts) / 3);
                for ($i = 0; $i < $numProductByOrder; $i++) {

                    // Sélectionner un produit aléatoire 
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

                    // Retirer le produit de la liste pour éviter les duplications
                    array_splice($supplierProducts, $randomIndexProduct, 1);
                }

            }
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
            Product::class,
        ];
    }


}
