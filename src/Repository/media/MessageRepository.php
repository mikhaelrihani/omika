<?php

namespace App\Repository\media;

use App\Entity\media\Message;
use App\Entity\user\Contact;
use App\Entity\user\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }
/**
     * Récupère tous les messages d'un destinataire donné.
     *
     * @param User|Contact $recipient L'entité destinataire (User ou Contact).
     * @return Message[] Retourne un tableau d'objets Message.
     */
    public function findMessagesByRecipient($recipient): array
    {
        // Vérifie si le destinataire est un utilisateur ou un contact
        if ($recipient instanceof User) {
            $recipientType = 'user';
        } elseif ($recipient instanceof Contact) {
            $recipientType = 'contact';
        } else {
            throw new \InvalidArgumentException('Recipient must be a User or Contact entity.');
        }

        // Exécute la requête pour récupérer les messages
        return $this->findBy([
            'recipientId' => $recipient->getId(),
            'recipientType' => $recipientType
        ]);
    }
    //    /**
    //     * @return Message[] Returns an array of Message objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Message
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
