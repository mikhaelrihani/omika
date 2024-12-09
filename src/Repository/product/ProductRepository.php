<?php

namespace App\Repository\Product;

use App\Entity\Product\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    //! ------------------------------------------------------------------------------------------------

    public function isProductExist(int $id, string $kitchenName): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.supplier = :id')
            ->andWhere('p.kitchenName = :kitchenName')
            ->setParameter('id', $id)
            ->setParameter('kitchenName', $kitchenName)
            ->getQuery()
            ->getResult();
    }

    //! ------------------------------------------------------------------------------------------------

    public function findproductSuppliers(Product $product): array
    {
        return $this->createQueryBuilder('p')
            ->select('s.id', 'b.name')
            ->join('p.supplier', 's')
            ->join('s.business', 'b')
            ->where('p.kitchenName = :kitchenName')
            ->setParameter('kitchenName', $product->getKitchenName())
            ->getQuery()
            ->getResult();
    }

    //! ------------------------------------------------------------------------------------------------


}
