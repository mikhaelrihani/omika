<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Entity\user\User;
use App\Repository\Event\UserEventsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserEventsRepository::class)]
class UserEvents extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(nullable: true, type: 'json')]
    #[Assert\Type(type: 'array', message: "The recurringEvents must be an array.")]
    private ?array $recurringEvents = null;

    #[ORM\Column(nullable: true, type: 'json')]
    #[Assert\Type(type: 'array', message: "The infoEvents must be an array.")]
    private ?array $infoEvents = null;

    #[ORM\Column(nullable: true, type: 'json')]
    #[Assert\Type(type: 'array', message: "The taskEvents must be an array.")]
    private ?array $taskEvents = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecurringEvents(): ?array
    {
        return $this->recurringEvents;
    }

    public function setRecurringEvents(?array $recurringEvents): static
    {
        $this->recurringEvents = $recurringEvents;

        return $this;
    }

    public function getInfoEvents(): ?array
    {
        return $this->infoEvents;
    }

    public function setInfoEvents(?array $infoEvents): static
    {
        $this->infoEvents = $infoEvents;

        return $this;
    }

    public function getTaskEvents(): ?array
    {
        return $this->taskEvents;
    }

    public function setTaskEvents(?array $taskEvents): static
    {
        $this->taskEvents = $taskEvents;

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
}
