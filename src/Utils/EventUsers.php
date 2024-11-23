<?php
namespace App\Utils;

use App\Entity\Event\Event;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;

/**
 * Récupère les utilisateurs partagés avec un événement.
 * @return Collection<User> Les utilisateurs partagés avec l'événement.
 * @throws \InvalidArgumentException Si les utilisateurs ne sont pas trouvés.
 */
class EventUsers
{
    public function getUsers(Event $event): Collection
    {
        $users = ($event->getType() === "task") ?
            $event->getTask()->getSharedWith() :
            $event->getInfo()->getSharedWith();

        if ($users instanceof PersistentCollection && !$users->isInitialized()) {
            $users->initialize();
        }
        if(!$users){
            throw new \InvalidArgumentException('Users not found');
        }
        return $users ?? new ArrayCollection();
    }
}
