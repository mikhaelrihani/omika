<?php

namespace App\Entity\event;

use App\Repository\event\IssueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\user\user; 
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IssueRepository::class)]
class Issue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $countNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $author = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?user $technicianContacted = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?user $technicianComing = null;

    #[ORM\Column(length: 50)]
    private ?string $summary = null;

    #[ORM\Column(length: 1000)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fixDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fixTime = null;

    #[ORM\Column(nullable: true)]
    private ?int $followUp = null;

    #[ORM\Column(length: 1000)]
    private ?string $solution = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCountNumber(): ?int
    {
        return $this->countNumber;
    }

    public function setCountNumber(int $countNumber): static
    {
        $this->countNumber = $countNumber;

        return $this;
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

    public function getTechnicianContacted(): ?user
    {
        return $this->technicianContacted;
    }

    public function setTechnicianContacted(user $technicianContacted): static
    {
        $this->technicianContacted = $technicianContacted;

        return $this;
    }

    public function getTechnicianComing(): ?user
    {
        return $this->technicianComing;
    }

    public function setTechnicianComing(?user $technicianComing): static
    {
        $this->technicianComing = $technicianComing;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getFixDate(): ?\DateTimeInterface
    {
        return $this->fixDate;
    }

    public function setFixDate(?\DateTimeInterface $fixDate): static
    {
        $this->fixDate = $fixDate;

        return $this;
    }

    public function getFixTime(): ?\DateTimeInterface
    {
        return $this->fixTime;
    }

    public function setFixTime(?\DateTimeInterface $fixTime): static
    {
        $this->fixTime = $fixTime;

        return $this;
    }

    public function getFollowUp(): ?int
    {
        return $this->followUp;
    }

    public function setFollowUp(?int $followUp): static
    {
        $this->followUp = $followUp;

        return $this;
    }

    public function getSolution(): ?string
    {
        return $this->solution;
    }

    public function setSolution(string $solution): static
    {
        $this->solution = $solution;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }
}
