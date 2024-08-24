<?php

namespace App\Entity\media;

use App\Entity\BaseEntity;
use App\Entity\user\Contact;
use App\Repository\media\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\user\User; 
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
    private ?User $user = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $recipient = null;

    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Text should not be blank.")]
    private ?string $text = null;

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

    public function getRecipient(): ?Contact
    {
        return $this->recipient;
    }

    public function setRecipient(Contact $recipient): static
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
