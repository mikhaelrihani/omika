<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\EventInfoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventInfoRepository::class)]
class EventInfo extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // Identifiant unique de l'information (hérité de Event)

    #[ORM\Column(type: 'json', nullable: true)]
    private array $unreadUsers = []; // Liste des utilisateurs n'ayant pas encore lu l'information

    #[ORM\Column(type: 'json', nullable: true)]
    private array $tag_info_active = []; // Comptabilisation des informations non lues pour chaque section et utilisateur par jour

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUnreadUsers(): array
    {
        return $this->unreadUsers;
    }

    public function setUnreadUsers(array $unreadUsers): static
    {
        $this->unreadUsers = $unreadUsers;
        return $this;
    }

    public function getTagInfoActive(): array
    {
        return $this->tag_info_active;
    }

    public function setTagInfoActive(array $tag_info_active): static
    {
        $this->tag_info_active = $tag_info_active;
        return $this;
    }
}
