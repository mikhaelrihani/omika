<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SecurityService
{
    private EntityManagerInterface $em;
    private UserProviderInterface $userProvider;

    public function __construct(EntityManagerInterface $em, UserProviderInterface $userProvider)
    {
        $this->em = $em;
        $this->userProvider = $userProvider;
    }

    public function refreshPassword(string $email, string $newPassword)
    {

        // récupérer le nouveau user password et le setter(haschage automatique)
        // flush the password
    }

    public function sendPasswordLink(string $email, string $link)
    {

        // verifier si l email existe et match avec un user
        // recuperer le user provider/userlogin entity grace a l email 
        // vérifier si le user est enabled ou autre security ou block user request
        // envoyer un email avec le lien(link recu par le frontend) du form de renouvellement de password 
        // utiliser un service email avec le lien
    }


}


