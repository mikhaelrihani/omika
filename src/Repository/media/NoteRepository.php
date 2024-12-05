<?php

namespace App\Repository\Media;

use App\Entity\Media\Note;
use App\Entity\User\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }


    public function findReceivedNotesByDate(DateTimeImmutable $date, int $userId): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.author = :userId')
            ->setParameter('userId', $userId)
            ->andWhere('n.createdAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult()
        ;
    }

}
