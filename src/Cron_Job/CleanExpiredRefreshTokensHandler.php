<?php
namespace App\MessageHandler;

use App\Message\CleanExpiredRefreshTokensMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanExpiredRefreshTokensHandler 
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function __invoke(CleanExpiredRefreshTokensMessage $message)
    {
        $now = new \DateTime();
        $tokens = $this->entityManager->getRepository(RefreshToken::class)
            ->findExpiredTokens($now); 

        foreach ($tokens as $token) {
            $this->entityManager->remove($token);
        }

        $this->entityManager->flush();
    }
}
