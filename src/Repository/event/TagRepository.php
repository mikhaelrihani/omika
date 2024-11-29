<?php

namespace App\Repository\Event;

use App\Entity\Event\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }


    public function findOneByDaySideSection($day, $side, $section, $type): ?Tag
    {

        if (!in_array($type, ['info', 'task'])) {
            throw new InvalidArgumentException('Invalid type provided. Expected "info" or "task".');
        }
        // Transforme la première lettre de $type en majuscule
        $type = ucfirst($type)."s";
     
        // Construire la requête avec un LEFT JOIN dynamique
        $query = $this->createQueryBuilder('t')
            ->leftJoin("t.tag$type", 'related') // Effectue le LEFT JOIN dynamique
            ->where('t.day = :day')
            ->andWhere('t.side = :side')
            ->andWhere('t.section = :section')
            ->setParameter('day', $day)
            ->setParameter('side', $side)
            ->setParameter('section', $section);

        return $query->getQuery()->getOneOrNullResult();
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
