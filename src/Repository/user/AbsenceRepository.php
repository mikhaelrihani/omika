<?php

namespace App\Repository\User;

use App\Entity\User\Absence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Absence>
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    /**
     * Finds all active absences for a given date.
     *
     * @param string $date The date to search for.
     *
     * @return Absence[] An array of Absence objects.
     */
    public function findByStatusAndDate(string $date): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->andWhere(':date BETWEEN a.startDate AND a.endDate')
            ->setParameter('status', 'active')
            ->setParameter('date', $date);

        return $qb->getQuery()->getResult();
    }

}


