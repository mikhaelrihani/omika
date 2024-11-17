<?php

namespace App\Repository\Event;

use App\Entity\Event\EventTask;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventTask>
 */
class EventTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventTask::class);
    }
    public function findByUserInSharedWith(User $user): array
    {
        return $this->createQueryBuilder('et')
            ->join('et.sharedWith', 'u')
            ->where('u = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
    

}
