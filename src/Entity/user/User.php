<?php

namespace App\Entity\User;

use App\Entity\BaseEntity;
use App\Entity\Event\Event;
use App\Entity\Media\Note;
use App\Repository\User\UserRepository;
use App\Validator\UniquePictureValidator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Media\Picture;
use App\Interface\entity\RecipientInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User extends BaseEntity implements RecipientInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['event', 'eventRecurring', 'user', "tag", 'absence', "note"])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, nullable: false)]
    #[Assert\NotBlank]
    private ?string $uuid = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "First name should not be blank.")]
    #[Groups(['user', 'absence', "note"])]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Surname should not be blank.")]
    #[Groups(['user', 'absence', "note"])]
    private ?string $surname = null;

    #[ORM\Column(length: 20, nullable: false)]
    #[Assert\NotBlank(message: "Phone number should not be blank.")]
    #[Assert\Length(
        min: 10,
        max: 20,
        minMessage: "Phone number must be at least {{ limit }} characters long.",
        maxMessage: "Phone number cannot be longer than {{ limit }} characters."
    )]
    #[Assert\Regex(
        pattern: "/^\+?[0-9\s]*$/",
        message: "Phone number should contain only digits, spaces, and an optional leading '+' sign."
    )]
    #[Groups(['user'])]
    private ?string $phone = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(
        min: 10,
        max: 20,
        minMessage: "WhatsApp number must be at least {{ limit }} characters long.",
        maxMessage: "WhatsApp number cannot be longer than {{ limit }} characters."
    )]
    #[Assert\Regex(
        pattern: "/^\+?[0-9\s]*$/",
        message: "WhatsApp number should contain only digits, spaces, and an optional leading '+' sign."
    )]
    #[Groups(['user'])]
    private ?string $whatsapp = null;

    #[ORM\ManyToOne(targetEntity: Business::class, inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user'])]
    private ?Business $business = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user'])]
    private ?string $job = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\NotNull(message: "Late count should not be null.")]
    #[Groups(['user'])]
    private int $lateCount = 0;


    //! differents properties than contact entity

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user'])]
    private ?UserLogin $userLogin = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[UniquePictureValidator]
    #[Assert\Email(message: "The Avatar name :'{{ value }}' is already used.")]
    #[Groups(['user'])]
    private ?picture $avatar = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Pseudo should not be blank.")]
    #[Groups(['user'])]
    private ?string $pseudo = null;

    /**
     * @var Collection<int, Absence>
     */
    #[ORM\OneToMany(targetEntity: Absence::class, mappedBy: 'user', cascade: ['remove'], orphanRemoval: true)]
    #[Groups(['user'])]
    private Collection $absences;

    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Private note should not be blank.")]
    #[Groups(['user'])]
    private ?string $privateNote = null;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\ManyToMany(targetEntity: Event::class, mappedBy: 'favoritedBy', cascade: ['remove'])]
    private Collection $favoriteEvents;

    /**
     * @var Collection<int, Note>
     */
    #[ORM\ManyToMany(targetEntity: Note::class, mappedBy: 'Recipients')]
    private Collection $receivedNotes;

    /**
     * @var Collection<int, Note>
     */
    #[ORM\OneToMany(targetEntity: Note::class, mappedBy: 'author', cascade: ['remove'], orphanRemoval: true)]
    private Collection $writtenNotes;


    public function __construct()
    {
        $this->absences = new ArrayCollection();
        $this->favoriteEvents = new ArrayCollection();
        $this->receivedNotes = new ArrayCollection();
        $this->writtenNotes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): static
    {
        $this->surname = $surname;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstname . ' ' . $this->surname;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getWhatsapp(): ?string
    {
        return $this->whatsapp;
    }

    public function setWhatsapp(?string $whatsapp): static
    {
        $this->whatsapp = $whatsapp;

        return $this;
    }

    public function getUserLogin(): ?UserLogin
    {
        return $this->userLogin;
    }

    public function setUserLogin(UserLogin $userLogin): static
    {
        $this->userLogin = $userLogin;

        return $this;
    }



    public function getAvatar(): ?picture
    {
        return $this->avatar;
    }

    public function setAvatar(?picture $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }



    public function getLateCount(): ?int
    {
        return $this->lateCount;
    }

    public function setLateCount(int $lateCount): static
    {
        $this->lateCount = $lateCount;

        return $this;
    }


    public function getJob(): ?string
    {
        return $this->job;
    }

    public function setJob(?string $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function getBusiness(): ?Business
    {
        return $this->business;
    }

    public function setBusiness(?Business $business): static
    {
        $this->business = $business;

        return $this;
    }

    /**
     * @return Collection<int, Absence>
     */
    public function getAbsences(): Collection
    {
        return $this->absences;
    }

    public function addAbsence(Absence $absence): static
    {
        if (!$this->absences->contains($absence)) {
            $this->absences->add($absence);
            $absence->setUser($this);
        }

        return $this;
    }

    public function removeAbsence(Absence $absence): static
    {
        if ($this->absences->removeElement($absence)) {
            // set the owning side to null (unless already changed)
            if ($absence->getUser() === $this) {
                $absence->setUser(null);
            }
        }

        return $this;
    }

    public function getPrivateNote(): ?string
    {
        return $this->privateNote;
    }

    public function setPrivateNote(string $privateNote): static
    {
        $this->privateNote = $privateNote;

        return $this;
    }


    public function getWrittenNotes(): Collection
    {
        return $this->writtenNotes;
    }

    public function addWrittenNote(Note $writtenNote): static
    {
        if (!$this->writtenNotes->contains($writtenNote)) {
            $this->writtenNotes->add($writtenNote);
            $writtenNote->setAuthor($this);
        }

        return $this;
    }
    /**
     * @return Collection<int, Event>
     */
    public function getFavoriteEvents(): Collection
    {
        return $this->favoriteEvents;
    }

    public function addFavoriteEvent(Event $favoriteEvent): static
    {
        if (!$this->favoriteEvents->contains($favoriteEvent)) {
            $this->favoriteEvents->add($favoriteEvent);
            $favoriteEvent->addFavoritedBy($this);
        }

        return $this;
    }

    public function removeFavoriteEvent(Event $favoriteEvent): static
    {
        if ($this->favoriteEvents->removeElement($favoriteEvent)) {
            $favoriteEvent->removeFavoritedBy($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Note>
     */
    public function getReceivedNotes(): Collection
    {
        return $this->receivedNotes;
    }

    public function addreceivedNote(Note $receivedNote): static
    {
        if (!$this->receivedNotes->contains($receivedNote)) {
            $this->receivedNotes->add($receivedNote);
        }

        return $this;
    }

    public function removeReceivedNote(Note $receivedNote): static
    {
        if ($this->receivedNotes->removeElement($receivedNote)) {
            $receivedNote->removeRecipient($this);
        }

        return $this;
    }

}
