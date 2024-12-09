<?php

namespace App\Repository\Supplier;

use App\Entity\Supplier\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Supplier>
 */
class SupplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    public function findSuppliersByCategorie(int $categoryId): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.id', 'b.name')
            ->leftJoin('s.business', 'b')
            ->join('s.categories', 'c')
            ->where('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getResult();
    }

}
