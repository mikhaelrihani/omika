<?php

namespace App\Entity\media;

use App\Entity\BaseEntity;
use App\Entity\user\Contact;
use App\Repository\media\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\user\User;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Message
 * 
 * Represents a message entity with a writer and a polymorphic recipient.
 * 
 * This class allows a message to have either a User or Contact as a recipient.
 * The recipient is identified by both an ID (`recipientId`) and a type (`recipientType`).
 * The `recipientType` determines which entity to fetch (either `User` or `Contact`).
 * 
 * @package App\Entity\media
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $writer = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $recipientId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private ?string $recipientType = null;

    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Text should not be blank.")]
    private ?string $text = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWriter(): ?User
    {
        return $this->writer;
    }

    public function setWriter(?User $writer): static
    {
        $this->writer = $writer;

        return $this;
    }
   
   public function getRecipientType(): ?string
   {
       return $this->recipientType;
   }

   
   public function setRecipientType(string $recipientType): self
   {
       if (!in_array($recipientType, ['user', 'contact'])) {
           throw new \InvalidArgumentException('Invalid recipient type');
       }

       $this->recipientType = $recipientType;

       return $this;
   }
   
    public function setRecipient(BaseEntity $recipient): self
    {
        if ($recipient instanceof User) {
            $this->recipientType = 'user';
        } elseif ($recipient instanceof Contact) {
            $this->recipientType = 'contact';
        } else {
            throw new \InvalidArgumentException('Invalid recipient type');
        }

        $this->recipientId = $recipient->getId();

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
