<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class EventInfo extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $tag_info_active_range = []; // Plage active des infos non lues

    #[ORM\Column(type: 'json', nullable: true)]
    private array $tag_info_off_range = []; // Infos non lues hors de la plage active

    #[ORM\Column(type: 'json', nullable: true)]
    private array $read_users = []; // Liste des utilisateurs ayant lu l'info

    #[ORM\OneToOne(inversedBy: "eventInfo", cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null; // Relation One-to-One avec Event

    // Getters et setters...
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTagInfoActiveRange(): array
    {
        return $this->tag_info_active_range;
    }

    public function setTagInfoActiveRange(?array $tag_info_active_range): static
    {
        $this->tag_info_active_range = $tag_info_active_range;
        return $this;
    }

    public function getTagInfoOffRange(): array
    {
        return $this->tag_info_off_range;
    }

    public function setTagInfoOffRange(?array $tag_info_off_range): static
    {
        $this->tag_info_off_range = $tag_info_off_range;
        return $this;
    }

    public function getReadUsers(): array
    {
        return $this->read_users;
    }

    public function setReadUsers(?array $read_users): static
    {
        $this->read_users = $read_users;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;
        return $this;
    }
}
