<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * @param \DateTimeInterface|null $datetime
     *
     * @return RefreshToken[]
     */
    public function findInvalid($datetime = null)
    {
        if (null === $datetime) {
            $datetime = new \DateTime();
        }
        return $this->createQueryBuilder('r')
            ->andWhere('r.valid <= :now')
            ->setParameter('now', $datetime)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find a refresh token by its token string.
     *
     * @param string $refreshToken
     * @return RefreshToken|null
     */
    public function findOneByRefreshToken(string $refreshToken): ?RefreshToken
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.refreshToken = :refreshToken')
            ->setParameter('refreshToken', $refreshToken)
            ->getQuery()
            ->getOneOrNullResult();  // Returns either the entity or null
    }
}
