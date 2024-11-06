<?php

namespace App\Repository\Event;

use App\Entity\Event\Tag;
use App\Entity\Event\TagInfo;
use App\Entity\user\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TagInfo>
 */
class TagInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TagInfo::class);
    }

    /**
     * Trouve une entité TagInfo par User et Tag.
     *
     * @param User $user
     * @param Tag $tag
     * @return TagInfo|null
     */
    public function findOneByUserAndTag(User $user, Tag $tag): ?TagInfo
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.tag = :tag')
            ->setParameter('user', $user)
            ->setParameter('tag', $tag)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
//     * @return TagInfo[] Returns an array of TagInfo objects
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

    //    public function findOneBySomeField($value): ?TagInfo
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
