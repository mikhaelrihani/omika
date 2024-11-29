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

    public function findEventsBySection(int $sectionId, string $type, DateTimeImmutable $dueDate): array
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.section', 's')
            ->where('s.id = :sectionId')
            ->andWhere('e.type = :type')
            ->andWhere('e.dueDate = :dueDate')
            ->setParameter('sectionId', $sectionId)
            ->setParameter('type', $type)
            ->setParameter('dueDate', $dueDate);

        return $qb->getQuery()->getResult();
    }

    public function findInfoEventsForUser(array $events, int $userId): array
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.info', 'ei')
            ->join('ei.sharedWith', 'ui')
            ->where('ui.user = :user')
            ->andWhere('e IN (:events)')
            ->setParameter('user', $userId)
            ->setParameter('events', $events);

        return $qb->getQuery()->getResult();
    }

    public function findTaskEventsForUser(array $events, int $userId): array
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.task', 'et')
            ->join('et.sharedWith', 'u')
            ->where('u.id = :user')
            ->andWhere('e IN (:events)')
            ->setParameter('user', $userId)
            ->setParameter('events', $events);

        return $qb->getQuery()->getResult();
    }

    public function findEventsBySectionTypeAndDueDateForUser(int $sectionId, string $type, DateTimeImmutable $dueDate, int $userId): array
    {
        // Étape 1 : Récupérer les événements de base
        $events = $this->findEventsBySection($sectionId, $type, $dueDate);

        if (empty($events)) {
            return [];
        }

        // Étape 2 : Filtrer selon le type
        if ($type === 'info') {
            return $this->findInfoEventsForUser($events, $userId);
          
        } elseif ($type === 'task') { 
            return $this->findTaskEventsForUser($events, $userId);
        }

        // Si le type est inconnu, retournez une liste vide
        return [];
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
    public function findEventsBySectionTypeAndDueDateForUserWithSQL(int $sectionId, string $type, DateTimeImmutable $dueDate, $user): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Requête SQL native
        $sql = "
            SELECT e.*
            FROM event e
            JOIN section s ON e.section_id = s.id
            WHERE s.id = :sectionId
              AND e.type = :type
              AND e.due_date = :dueDate
              AND (
                (e.type = 'info' AND EXISTS (
                  SELECT 1
                  FROM event_info ei
                  JOIN user_info ui ON ei.id = ui.event_info_id
                  WHERE ei.id = e.info_id
                    AND ui.user_id = :user
                ))
                OR (e.type = 'task' AND EXISTS (
                  SELECT 1
                  FROM event_task et
                  JOIN user_task ut ON et.id = ut.event_task_id
                  WHERE et.id = e.task_id
                    AND ut.user_id = :user
                ))
              )
        ";

        // Paramètres pour la requête SQL
        $params = [
            'sectionId' => $sectionId,
            'type'      => $type,
            'dueDate'   => $dueDate->format('Y-m-d'),
            'user'      => $user,
        ];

        // Exécuter la requête SQL
        $stmt = $conn->prepare($sql);
        $resultSet = $stmt->executeQuery($params);
        // Retourner les résultats sous forme de tableau
        return $resultSet->fetchAllAssociative();
    }
  
}


//! lorsque un user realise une tache comme passer une cde ou ecrire une info , 
// il peut decider d'enregistrer ou de mettre en pending.mais un event doit etre associer ou  creer.
// dans le cas ou l'event existe l'event reste partage avce ces users associé meme en pending.
// dans le cas ou l'event doit etre creer alors on doit demander a l'user de remplir les champs necessaires a la création
// puis si il veut publier cet event pour tous les users partagé ou le garder en pending pour lui jusqua ce que l event lui plaise
// cad que l'event lui sera visible chez lui de suite mais plus tard pour les autres .
// par ex je prepare une cde je veux qu'elle soit publier pour tous de suite , par contre si je prepare une info je veux d'abord la garder chez moi puis la publier.

// on doit donc lorsque l on fait une recherche par section , verifier que le ispublished est vrai pour le rendre visible aux user partage.
// creer une propriete ispublished
// verifeir que la propriete isPending est passe a false lorsque l'event est done,sauf si l event est past pour le cronjob
// lorsque l'event est en ispublished false l'event a un status pending
// ajouter un viewId/link pour afficher un lien direct dans l'event, ainsi que dans la vue de l'event, pointant vers la tache lié(cde, inventaire...) qui peut etre null dans le cas d'une info
// ce view id correspond a l'id de la tache "cde, inventaire..." qui est lié a l'event comme ca on peut rechecher la vue de la tache  liée a l'event