<?php

namespace App\Entity\event;

use App\Entity\BaseEntity;
use App\Entity\user\Contact;
use App\Repository\event\IssueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\user\user; 
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IssueRepository::class)]
class Issue extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable:false)]
    #[Assert\NotBlank]
    private ?int $countNumber = null;

    #[ORM\Column(length: 255, nullable:false)]
    #[Assert\NotBlank]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable:false)]
    #[Assert\NotBlank]
    private ?string $author = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $technicianContacted = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    private ?Contact $technicianComing = null;

    #[ORM\Column(length: 50, nullable:false)]
    #[Assert\NotBlank]
    private ?string $summary = null;

    #[ORM\Column(length: 1000, nullable:false)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fixDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fixTime = null;

    #[ORM\Column(nullable: true)]
    private ?int $followUp = null;

    #[ORM\Column(length: 1000, nullable:true)]
    private ?string $solution = null;

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

    public function getTechnicianContacted(): ?Contact
    {
        return $this->technicianContacted;
    }

    public function setTechnicianContacted(Contact $technicianContacted): static
    {
        $this->technicianContacted = $technicianContacted;

        return $this;
    }

    public function getTechnicianComing(): ?Contact
    {
        return $this->technicianComing;
    }

    public function setTechnicianComing(?Contact $technicianComing): static
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

  
}
