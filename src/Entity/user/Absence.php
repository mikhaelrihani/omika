<?php

namespace App\Entity\user;

use App\Entity\BaseEntity;
use App\Repository\user\AbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
class Absence extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Status should not be blank.")]
    private ?string $status = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Author name should not be blank.")]
    private ?string $author = null;

    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Reason should not be blank.")]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotBlank(message: "The start date should not be blank.")]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotBlank(message: "The end date should not be blank.")]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(nullable: false)]
    private ?bool $planningUpdate = null;

    

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?user $staff = null;

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
    
    public function getStaff(): ?user
    {
        return $this->staff;
    }

    public function setStaff(user $staff): static
    {
        $this->staff = $staff;

        return $this;
    }
}
