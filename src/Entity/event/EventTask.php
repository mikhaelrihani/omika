<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class EventTask extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank(message: "Task details should not be blank.")]
    private ?string $task_details = null; // Détails spécifiques à la tâche

    #[ORM\Column(type: 'json', nullable: true)]
    private array $task_status_active_range = []; // Statuts des tâches actives

    #[ORM\Column(type: 'json', nullable: true)]
    private array $task_status_off_range = []; // Statuts des tâches hors de la plage active

    #[ORM\OneToOne(inversedBy: "eventTask", cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null; // Relation One-to-One avec Event

    // Getters et setters...
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskDetails(): ?string
    {
        return $this->task_details;
    }

    public function setTaskDetails(string $task_details): static
    {
        $this->task_details = $task_details;
        return $this;
    }

    public function getTaskStatusActiveRange(): array
    {
        return $this->task_status_active_range;
    }

    public function setTaskStatusActiveRange(?array $task_status_active_range): static
    {
        $this->task_status_active_range = $task_status_active_range;
        return $this;
    }

    public function getTaskStatusOffRange(): array
    {
        return $this->task_status_off_range;
    }

    public function setTaskStatusOffRange(?array $task_status_off_range): static
    {
        $this->task_status_off_range = $task_status_off_range;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;
        return $this;
    }
}
