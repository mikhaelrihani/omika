<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SecurityService
{
    private EntityManagerInterface $em;
    private UserProviderInterface $userProvider;
    private EmailFacadeService $emailFacadeService;


    public function __construct(EntityManagerInterface $em, UserProviderInterface $userProvider, EmailFacadeService $emailFacadeService)
    {
        $this->em = $em;
        $this->userProvider = $userProvider;
        $this->emailFacadeService = $emailFacadeService;
    }

    public function refreshPassword(string $email, string $newPassword)
    {

        // récupérer le nouveau user password et le setter(haschage automatique)
        // flush the password
    }

    public function sendPasswordLink(string $email, string $link)
    {
        $user = $this->userProvider->loadUserByIdentifier($email);
        if (!$user) {
            throw new \Exception('User not found, check the email value');
        }
        ($user->isEnabled()) ? $this->emailFacadeService->sendPasswordLink() : throw new \Exception('User is not enabled, so we cannot renew the password');
    
    }


}


