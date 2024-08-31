<?php

namespace App\Entity\user;

use App\Entity\BaseEntity;
use App\Entity\RecipientInterface;
use App\Repository\User\ContactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_EMAIL', fields: ['email'])]

class Contact extends BaseEntity implements RecipientInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, nullable: false)]
    #[Assert\NotBlank]
    private ?string $uuid = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "First name should not be blank.")]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Surname should not be blank.")]
    private ?string $surname = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "Email should not be blank.")]
    #[Assert\Email(message: "The email '{{ value }}' is not a valid email.")]
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
    private ?string $whatsapp = null;


    #[ORM\ManyToOne(targetEntity: Business::class, inversedBy: 'contacts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Business $business = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Job should not be blank.")]
    private ?string $job = null;

    #[ORM\Column(nullable: true)]
    private ?int $lateCount = null;

    /**
     * @var Collection<int, Absence>
     */
    #[ORM\OneToMany(targetEntity: Absence::class, mappedBy: 'contact')]
    private Collection $absence;

    public function __construct()
    {
        parent::__construct();
        $this->absence = new ArrayCollection();
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
    public function getAbsence(): Collection
    {
        return $this->absence;
    }

    public function addAbsence(Absence $absence): static
    {
        if (!$this->absence->contains($absence)) {
            $this->absence->add($absence);
            $absence->setContact($this);
        }

        return $this;
    }

    public function removeAbsence(Absence $absence): static
    {
        if ($this->absence->removeElement($absence)) {
            // set the owning side to null (unless already changed)
            if ($absence->getContact() === $this) {
                $absence->setContact(null);
            }
        }

        return $this;
    }



}
