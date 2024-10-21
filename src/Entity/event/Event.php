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
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Type should not be blank.")]
    private ?string $type = null; // Type d'événement (task ou info)

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank(message: "Importance should not be blank.")]
    private ?bool $importance = null; // Indique si l'événement est important

    #[ORM\Column(type: 'json')]
    private array $shared_with = []; // Tableau JSON des utilisateurs

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $date_created = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $date_limit = null; // Date limite pour la visibilité

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Status should not be blank.")]
    private ?string $status = null; // Statut de l'événement

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank(message: "Active day range should not be blank.")]
    private ?int $active_day_range = null; // Plage de jours actifs

    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank(message: "Description should not be blank.")]
    private ?string $description = null; // Détails de l'événement

    // Relation One-to-One avec EventTask
    #[ORM\OneToOne(mappedBy: "event", cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?EventTask $eventTask = null;

    // Relation One-to-One avec EventInfo
    #[ORM\OneToOne(mappedBy: "event", cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?EventInfo $eventInfo = null;

    // Getters et setters...
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

    public function getActiveDayRange(): ?int
    {
        return $this->active_day_range;
    }

    public function setActiveDayRange(int $active_day_range): static
    {
        $this->active_day_range = $active_day_range;
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

    public function getEventTask(): ?EventTask
    {
        return $this->eventTask;
    }

    public function setEventTask(?EventTask $eventTask): static
    {
        // Associer l'EventTask à l'Event
        $this->eventTask = $eventTask;
    
        // Si une tâche est associée, configurez l'EventTask pour référencer cet Event
        if ($eventTask !== null) {
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
        // Associer l'EventInfo à l'Event
        $this->eventInfo = $eventInfo;
    
        // Si une info est associée, configurez l'EventInfo pour référencer cet Event
        if ($eventInfo !== null) {
            $eventInfo->setEvent($this);
        }
    
        return $this;
    }
    
}
