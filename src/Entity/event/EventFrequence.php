<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\EventFrequenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventFrequenceRepository::class)]
class EventFrequence extends BaseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    private string $id; // Identifiant unique de la fréquence

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 8,
        notInRangeMessage: 'Day must be between {{ min }} and {{ max }}. Use 8 for unlimited.'
    )]
    private ?int $day = null; // Jour associé à la fréquence (1 = lundi, 7 = dimanche, 8 = illimité)

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 31,
        notInRangeMessage: 'Month day must be between {{ min }} and {{ max }}.'
    )]
    private ?int $monthDay = null; // Jour du mois associé à la fréquence (1 à 31)

    // Getters and Setters

    public function getId(): string
    {
        return $this->id;
    }

    public function getDay(): ?int
    {
        return $this->day;
    }

    public function setDay(?int $day): static
    {
        $this->day = $day;
        return $this;
    }

    public function getMonthDay(): ?int
    {
        return $this->monthDay;
    }

    public function setMonthDay(?int $monthDay): static
    {
        $this->monthDay = $monthDay;
        return $this;
    }
}
