<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Entity\User\User;
use App\Repository\Event\UserInfoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserInfoRepository::class)]
class UserInfo extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: true)] // Doit être nullable pour permettre de mettre user à null
    private ?User $user = null;
    
    #[ORM\ManyToOne(inversedBy: 'sharedWith')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EventInfo $eventInfo = null;

    #[ORM\Column]
    private ?bool $isRead = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getEventInfo(): ?EventInfo
    {
        return $this->eventInfo;
    }

    public function setEventInfo(?EventInfo $eventInfo): static
    {
        $this->eventInfo = $eventInfo;

        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->isRead;
    }

    public function setisRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }




}
