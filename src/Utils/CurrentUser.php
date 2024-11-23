<?php
namespace App\Utils;

use App\Entity\User\User;
use App\Entity\User\UserLogin;
use Symfony\Bundle\SecurityBundle\Security;
use App\Repository\User\UserRepository;


/**
     * Récupère l'utilisateur connecté.
     * @return User|null L'utilisateur actuellement connecté ou null si l'utilisateur n'est pas trouvé.
     *
     * @throws \LogicException Si l'utilisateur n'est pas connecté.
     * @throws \InvalidArgumentException Si l'utilisateur n'est pas trouvé dans la base de données.
     */
class CurrentUser
{
    public function __construct(private Security $security, private UserRepository $userRepository){
     
    }

    public function getCurrentUser(): ?User
    {
        $userLogin = $this->security->getUser();
        if (!$userLogin instanceof UserLogin) {
            throw new \LogicException('User must be logged in');
        }

        $user = $this->userRepository->find($userLogin->getId());
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        return $user;
    }
}
