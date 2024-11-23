<?php

namespace App\Repository\Event;

use App\Entity\Event\Event;
use App\Entity\User\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Récupère les événements d'une section en fonction du type, dueDate, et utilisateur.
     *
     * @param int      $sectionId
     * @param string   $type
     * @param \DateTime $dueDate
     * @param User     $user
     * @return Event[]
     */
    public function findEventsBySectionTypeAndDueDateForUser(int $sectionId, string $type, DateTimeImmutable $dueDate, User $user): array
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.section', 's')
            ->where('s.id = :sectionId')
            ->andWhere('e.type = :type')
            ->andWhere('e.dueDate <= :dueDate')
            ->setParameter('sectionId', $sectionId)
            ->setParameter('type', $type)
            ->setParameter('dueDate', $dueDate);

        if ($type === 'info') {
            $qb->join('e.eventInfo', 'ei')
                ->join('ei.userInfos', 'ui')
                ->andWhere('ui.user = :user');
        } elseif ($type === 'task') {
            $qb->join('e.eventTask', 'et')
                ->join('et.users', 'u')
                ->andWhere('u = :user');
        }

        $qb->setParameter('user', $user);

        return $qb->getQuery()->getResult();
    }
}


