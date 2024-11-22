<?php

namespace App\Repository\Event;

use App\Entity\Event\Tag;
use App\Entity\Event\TagTask;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TagTask>
 */
class TagTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TagTask::class);
    }

    /**
     * Trouve une entité TagTask par User et Tag.
     *
     * @param User $user
     * @param Tag $tag
     * @return TagTask|null
     */
    public function findOneByUserAndTag_task(User $user, Tag $tag): ?TagTask
    {
        return $this->createQueryBuilder('tt')
            ->andWhere('tt.user = :user')
            ->andWhere('tt.tag = :tag')
            ->setParameter('user', $user)
            ->setParameter('tag', $tag)
            ->getQuery()
            ->getOneOrNullResult();
    }
    
    //    /**
    //     * @return TagTask[] Returns an array of TagTask objects
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

    //    public function findOneBySomeField($value): ?TagTask
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
