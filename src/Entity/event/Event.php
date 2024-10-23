<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\EventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Type should not be blank.")]
    private ?string $type = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    #[Assert\NotNull]
    private ?bool $importance = null;

    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank(message: "Description should not be blank.")]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $shared_with = [];

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "CreatedBy should not be blank.")]
    private ?string $createdBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $updatedBy = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $periode_start = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $periode_end = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Date status should not be blank.")]
    private ?string $date_status = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $isRecurring = false;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $ispseudo_recurring = false;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Side should not be blank.")]
    private ?string $side = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $date_limit = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $active_day_range = [];

    #[ORM\ManyToOne(targetEntity: Section::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Section $section = null;

    #[ORM\OneToOne(mappedBy: 'event', targetEntity: EventTask::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?EventTask $task = null;

    #[ORM\OneToOne(mappedBy: 'event', targetEntity: EventInfo::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?EventInfo $info = null;

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getImportance(): ?bool
    {
        return $this->importance;
    }

    public function setImportance(bool $importance): static
    {
        $this->importance = $importance;
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

    public function getSharedWith(): array
    {
        return $this->shared_with;
    }

    public function setSharedWith(array $shared_with): static
    {
        $this->shared_with = $shared_with;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): static
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function getPeriodeStart(): ?\DateTimeInterface
    {
        return $this->periode_start;
    }

    public function setPeriodeStart(\DateTimeInterface $periode_start): static
    {
        $this->periode_start = $periode_start;
        return $this;
    }

    public function getPeriodeEnd(): ?\DateTimeInterface
    {
        return $this->periode_end;
    }

    public function setPeriodeEnd(?\DateTimeInterface $periode_end): static
    {
        $this->periode_end = $periode_end;
        return $this;
    }

    public function getDateStatus(): ?string
    {
        return $this->date_status;
    }

    public function setDateStatus(string $date_status): static
    {
        $this->date_status = $date_status;
        return $this;
    }

    public function isRecurring(): ?bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function isPseudoRecurring(): ?bool
    {
        return $this->ispseudo_recurring;
    }

    public function setIsPseudoRecurring(bool $ispseudo_recurring): static
    {
        $this->ispseudo_recurring = $ispseudo_recurring;
        return $this;
    }

    public function getSide(): ?string
    {
        return $this->side;
    }

    public function setSide(string $side): static
    {
        $this->side = $side;
        return $this;
    }

    public function getDateLimit(): ?\DateTimeInterface
    {
        return $this->date_limit;
    }

    public function setDateLimit(\DateTimeInterface $date_limit): static
    {
        $this->date_limit = $date_limit;
        return $this;
    }

    public function getActiveDayRange(): array
    {
        return $this->active_day_range;
    }

    public function setActiveDayRange(array $active_day_range): static
    {
        $this->active_day_range = $active_day_range;
        return $this;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(Section $section): static
    {
        $this->section = $section;
        return $this;
    }

    public function getTask(): ?EventTask
    {
        return $this->task;
    }

    public function setTask(EventTask $task): static
    {
        $this->task = $task;
        return $this;
    }

    public function getInfo(): ?EventInfo
    {
        return $this->info;
    }

    public function setInfo(EventInfo $info): static
    {
        $this->info = $info;
        return $this;
    }
}
