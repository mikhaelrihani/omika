<?php

namespace App\Entity\event;

use App\Entity\BaseEntity;
use App\Repository\event\EventFrequenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventFrequenceRepository::class)]
class EventFrequence extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $everyday = null;

    #[ORM\Column(type: Types::JSON)]
    private array $weekDays = [];

    #[ORM\Column]
    private ?int $monthDay = null;

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

}
