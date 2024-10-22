<?php

namespace App\Entity\event;

use App\Entity\BaseEntity;
use App\Repository\event\EventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Type should not be blank.")]
    private ?string $type = null; // info or task

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank(message: "Importance should not be blank.")]
    private ?bool $importance = null; // Indicates if the event is important

    #[ORM\Column(type: 'json', nullable: true)]
    private array $shared_with = []; // Shared visibility as array of user IDs

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $date_created = null; // Date of event creation

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $date_limit = null; // Date limit for visibility

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Status should not be blank.")]
    private ?string $status = null; // Status of the event

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Description should not be blank.")]
    private ?string $description = null; // Description of the event

    #[ORM\ManyToOne(targetEntity: EventSection::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EventSection $eventSection = null; // Related Event Section

    #[ORM\ManyToOne(targetEntity: EventFrequence::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EventFrequence $eventFrequence = null; // Related Event Frequency

    #[ORM\OneToOne(targetEntity: EventTask::class, mappedBy: 'event', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?EventTask $eventTask = null; // Related Event Task

    #[ORM\OneToOne(targetEntity: EventInfo::class, mappedBy: 'event', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?EventInfo $eventInfo = null; // Related Event Info

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Author should not be blank.")]
    private ?string $author = null; // Author of the event

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Side should not be blank.")]
    private ?string $side = null; // Side of the event

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: false)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $periode_start = null; // Start date of the event period

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $periode_end = null; // End date of the event period

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank]
    private ?bool $periode_unlimited = null; // Indicates if the period is unlimited

    #[ORM\Column(type: 'json', nullable: true)]
    private array $active_day_range = []; // Plage de jours actifs (par exemple, [-3, -2, -1, 0, 1, 2, 3, 4, 5, 6, 7])
    


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

    public function isImportance(): ?bool
    {
        return $this->importance;
    }

    public function setImportance(bool $importance): static
    {
        $this->importance = $importance;
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

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->date_created;
    }

    public function setDateCreated(\DateTimeInterface $date_created): static
    {
        $this->date_created = $date_created;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getEventSection(): ?EventSection
    {
        return $this->eventSection;
    }

    public function setEventSection(EventSection $eventSection): static
    {
        $this->eventSection = $eventSection;
        return $this;
    }

    public function getEventFrequence(): ?EventFrequence
    {
        return $this->eventFrequence;
    }

    public function setEventFrequence(EventFrequence $eventFrequence): static
    {
        $this->eventFrequence = $eventFrequence;
        return $this;
    }

    public function getEventTask(): ?EventTask
    {
        return $this->eventTask;
    }

    public function setEventTask(?EventTask $eventTask): static
    {
        $this->eventTask = $eventTask;

        if ($eventTask !== null && $eventTask->getEvent() !== $this) {
            $eventTask->setEvent($this);
        }

        return $this;
    }

    public function getEventInfo(): ?EventInfo
    {
        return $this->eventInfo;
    }

    public function setEventInfo(?EventInfo $eventInfo): static
    {
        $this->eventInfo = $eventInfo;

        if ($eventInfo !== null && $eventInfo->getEvent() !== $this) {
            $eventInfo->setEvent($this);
        }

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

    public function getSide(): ?string
    {
        return $this->side;
    }

    public function setSide(string $side): static
    {
        $this->side = $side;
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

    public function isPeriodeUnlimited(): ?bool
    {
        return $this->periode_unlimited;
    }

    public function setPeriodeUnlimited(bool $periode_unlimited): static
    {
        $this->periode_unlimited = $periode_unlimited;
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


}
