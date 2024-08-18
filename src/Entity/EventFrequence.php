<?php

namespace App\Entity;

use App\Repository\EventFrequenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventFrequenceRepository::class)]
class EventFrequence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $everyday = null;

    #[ORM\Column(type: Types::ARRAY)]
    private array $weekDays = [];

    #[ORM\Column]
    private ?int $monthDay = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEveryday(): ?bool
    {
        return $this->everyday;
    }

    public function setEveryday(bool $everyday): static
    {
        $this->everyday = $everyday;

        return $this;
    }

    public function getWeekDays(): array
    {
        return $this->weekDays;
    }

    public function setWeekDays(array $weekDays): static
    {
        $this->weekDays = $weekDays;

        return $this;
    }

    public function getMonthDay(): ?int
    {
        return $this->monthDay;
    }

    public function setMonthDay(int $monthDay): static
    {
        $this->monthDay = $monthDay;

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
