<?php

namespace App\Entity\User;

use App\Entity\BaseEntity;
use App\Repository\User\AbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\Index(
    name: 'absence_search_idx',
    columns: ['status', 'user_id', 'contact_id', 'start_date', 'end_date']
)]
class Absence extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user', 'contact','absence'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Status should not be blank.")]
    #[Groups(['absence'])]
    private ?string $status = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Author name should not be blank.")]
    #[Groups(['absence'])]
    private ?string $author = null;

    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Reason should not be blank.")]
    #[Groups(['absence'])]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotBlank(message: "The start date should not be blank.")]
    #[Groups(['absence'])]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotBlank(message: "The end date should not be blank.")]
    #[Groups(['absence'])]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(nullable: false)]
    #[Groups(['absence'])]
    private ?bool $planningUpdate = null;

    #[ORM\ManyToOne(inversedBy: 'absence')]
    #[Groups(['absence'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'absence')]
    #[Groups(['absence'])]
    private ?Contact $contact = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function isPlanningUpdate(): ?bool
    {
        return $this->planningUpdate;
    }

    public function setPlanningUpdate(bool $planningUpdate): static
    {
        $this->planningUpdate = $planningUpdate;

        return $this;
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

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }


}
