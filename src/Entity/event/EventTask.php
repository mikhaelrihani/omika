<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\EventTaskRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventTaskRepository::class)]
class EventTask extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // Identifiant unique de la tâche (hérité de Event)

    // #[ORM\Column(length: 100,type: 'string', nullable: true)]
    // private ?string $task_details = null; // Détails supplémentaires concernant la tâche si besoin.

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Task status should not be blank.")]
    private ?string $task_status = null; // Statut de la tâche (todo, pending, done, late, unrealised, warning, modified)


    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    // public function getTaskDetails(): ?string
    // {
    //     return $this->task_details;
    // }

    // public function setTaskDetails(?string $task_details): static
    // {
    //     $this->task_details = $task_details;
    //     return $this;
    // }

    public function getTaskStatus(): ?string
    {
        return $this->task_status;
    }

    public function setTaskStatus(string $task_status): static
    {
        $this->task_status = $task_status;
        return $this;
    }

  
}
