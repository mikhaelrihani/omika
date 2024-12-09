<?php

namespace App\Repository\Recipe;

use App\Entity\Product\Product;
use App\Entity\Recipe\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    public function findProductRecipes(Product $product): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.id', 'r.name', 'r.path')
            ->join('r.ingredients', 'i')
            ->where('i.name = :kitchenName')
            ->setParameter('kitchenName', $product->getKitchenName())
            ->groupBy('r.id', 'r.name', 'r.path')
            ->getQuery()
            ->getResult();
    }
}
