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
    #[ORM\Column(type: 'string')]
    private ?string $id = null; // Identifiant unique de l'événement

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Type should not be blank.")]
    private ?string $type = null; // Type d'événement (task ou info)

    #[ORM\Column(type: 'boolean', nullable: false)]
    #[Assert\NotNull]
    private ?bool $importance = null; // Indique si l'événement est important

    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank(message: "Description should not be blank.")]
    private ?string $description = null; // Détails de l'événement

    #[ORM\Column(type: 'json', nullable: true)]
    private array $shared_with = []; // Liste des utilisateurs avec qui l'événement est partagé

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "CreatedBy should not be blank.")]
    private ?string $createdBy = null; // Auteur de l'événement

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $updatedBy = null; // Dernier auteur ayant modifié l'événement

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $periode_start = null; // Date de début de la période

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $periode_end = null; // Date de fin de la période

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Date status should not be blank.")]
    private ?string $date_status = null; // Statut de la date (past, activedayrange, future)

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $isRecurring = false; // Indique si l'événement est récurrent

    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $ispseudo_recurring = false; // Indique si l'événement est pseudo-récurrent

    #[ORM\ManyToOne(targetEntity: EventFrequence::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EventFrequence $event_frequence = null; // Relation One-to-One avec Event_Frequence

    #[ORM\Column(type: 'json', nullable: true)]
    private array $task_details = []; // Détails de la tâche associée (si type = task)

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $task_status = null; // Statut de la tâche (ex. todo, pending, done, late)

    #[ORM\Column(type: 'json', nullable: true)]
    private array $unreadUsers = []; // Liste des utilisateurs n'ayant pas lu l'événement (si type = info)

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Side should not be blank.")]
    private ?string $side = null; // Côté de l'événement (ex. "kitchen", "office")

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $date_limit = null; // Date limite pour la visibilité (pour tâches automatisées)

    #[ORM\Column(type: 'json', nullable: true)]
    private array $active_day_range = []; // Plage de jours actifs (ex. -3 à +7 jours)

    #[ORM\ManyToOne(targetEntity: EventSection::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private ?EventSection $event_section = null; // Relation One-to-One avec Event_Section

    // Getters and Setters

    public function getId(): ?string
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

    public function getDescription(): ?string // Getter pour description
    {
        return $this->description;
    }

    public function setDescription(string $description): static // Setter pour description
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

    public function setPseudoRecurring(bool $ispseudo_recurring): static
    {
        $this->ispseudo_recurring = $ispseudo_recurring;
        return $this;
    }

    public function getEventFrequence(): ?EventFrequence
    {
        return $this->event_frequence;
    }

    public function setEventFrequence(EventFrequence $eventFrequence): static
    {
        $this->event_frequence = $eventFrequence;
        return $this;
    }

    public function getTaskDetails(): array
    {
        return $this->task_details;
    }

    public function setTaskDetails(array $task_details): static
    {
        $this->task_details = $task_details;
        return $this;
    }

    public function getTaskStatus(): ?string
    {
        return $this->task_status;
    }

    public function setTaskStatus(?string $task_status): static
    {
        $this->task_status = $task_status;
        return $this;
    }

    public function getUnreadUsers(): array
    {
        return $this->unreadUsers;
    }

    public function setUnreadUsers(array $unreadUsers): static
    {
        $this->unreadUsers = $unreadUsers;
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

    public function getEventSection(): ?EventSection
    {
        return $this->event_section;
    }

    public function setEventSection(EventSection $event_section): static
    {
        $this->event_section = $event_section;
        return $this;
    }
}
