<?php

namespace App\Repository\Event;

use App\Entity\Event\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    // src/Repository/TagRepository.php
    public function findOneByDaySideSection($day, $side, $section): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->where('t.day = :day')
            ->andWhere('t.side = :side')
            ->andWhere('t.section = :section')
            ->setParameter('day', $day)
            ->setParameter('side', $side)
            ->setParameter('section', $section)
            ->getQuery()
            ->getOneOrNullResult();
    }


    //    /**
    //     * @return Tag[] Returns an array of Tag objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Tag
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
