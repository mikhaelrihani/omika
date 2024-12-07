<?php

namespace App\Entity\Media;

use App\Entity\BaseEntity;
use App\Repository\Media\NoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User\User;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NoteRepository::class)]
class Note extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["note"])]
    private ?int $id = null;

    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Text should not be blank.")]
    #[Groups(["note"])]
    private ?string $text = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'writtenNotes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(["note"])]
    private ?User $author = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'receivedNotes')]
    #[Groups(["note"])]
    private Collection $Recipients;

    public function __construct()
    {

        $this->Recipients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getRecipients(): Collection
    {
        return $this->Recipients;
    }

    public function addRecipient(User $recipient): static
    {
        if (!$this->Recipients->contains($recipient)) {
            $this->Recipients->add($recipient);
            $recipient->addreceivedNote($this);
        }

        return $this;
    }

    public function removeRecipient(User $recipient): static
    {
        $recipient->removeReceivedNote($this);
        $this->Recipients->removeElement($recipient);

        return $this;
    }


}
