<?php

namespace App\Entity\User;

use App\Entity\BaseEntity;
use App\Entity\Media\Picture;
use App\Interface\entity\RecipientInterface;
use App\Repository\User\ContactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_EMAIL', fields: ['email'])]

class Contact extends BaseEntity implements RecipientInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['contact', 'absence',"supplier"])]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, nullable: false)]
    #[Assert\NotBlank]
    private ?string $uuid = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "First name should not be blank.")]
    #[Groups(['contact', 'absence',"supplier"])]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Surname should not be blank.")]
    #[Groups(['contact', 'absence',"supplier"])]
    private ?string $surname = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "Email should not be blank.")]
    #[Assert\Email(message: "The email '{{ value }}' is not a valid email.")]
    #[Groups(['contact', 'absence'])]
    private ?string $email = null;

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
    #[Groups(['contact'])]
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
    #[Groups(['contact'])]
    private ?string $whatsapp = null;


    #[ORM\ManyToOne(targetEntity: Business::class, inversedBy: 'contacts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['contact'])]
    private ?Business $business = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Job should not be blank.")]
    #[Groups(['contact'])]
    private ?string $job = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['contact'])]
    private ?int $lateCount = null;

    /**
     * @var Collection<int, Absence>
     */
    #[ORM\OneToMany(targetEntity: Absence::class, mappedBy: 'contact', cascade: ['remove'],orphanRemoval: true)]
    #[Groups(['contact'])]
    private Collection $absences;

    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Picture $avatar = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $emailTemplate = null;

    public function __construct()
    {
        $this->absences = new ArrayCollection();
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

    public function getBusiness(): ?Business
    {
        return $this->business;
    }

    public function setBusiness(?Business $business): static
    {
        $this->business = $business;

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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getJob(): ?string
    {
        return $this->job;
    }

    public function setJob(string $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function getLateCount(): ?int
    {
        return $this->lateCount;
    }

    public function setLateCount(?int $lateCount): static
    {
        $this->lateCount = $lateCount;

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
            $absence->setContact($this);
        }

        return $this;
    }

    public function removeAbsence(Absence $absence): static
    {
        $this->absences->removeElement($absence);
        return $this;
    }

    public function getAvatar(): ?Picture
    {
        return $this->avatar;
    }

    public function setAvatar(?Picture $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getEmailTemplate(): ?string
    {
        return $this->emailTemplate;
    }

    public function setEmailTemplate(?string $emailTemplate): static
    {
        $this->emailTemplate = $emailTemplate;

        return $this;
    }



}
