<?php

namespace App\Entity\media;

use App\Entity\BaseEntity;
use App\Repository\media\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\user\user; 
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?user $user = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?user $recipient = null;

    #[ORM\Column(length: 1000)]
    private ?string $text = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?user
    {
        return $this->user;
    }

    public function setUser(?user $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRecipient(): ?user
    {
        return $this->recipient;
    }

    public function setRecipient(user $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    
}
