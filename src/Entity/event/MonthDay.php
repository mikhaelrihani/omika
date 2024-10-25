<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class MonthDay extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "integer")]
    #[Assert\Range(min: 1, max: 31, notInRangeMessage: "Le jour du mois doit être entre 1 et 31.")]
    private int $day;

    #[ORM\ManyToMany(targetEntity: EventRecurring::class, mappedBy: 'monthDays')]
    private Collection $eventRecurrings;

    public function __construct(int $day)
    {
        $this->day = $day;
        $this->eventRecurrings = new ArrayCollection();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDay(): int
    {
        return $this->day;
    }

    public function setDay(int $day): static
    {
        $this->day = $day;
        return $this;
    }

    public function getEventRecurrings(): Collection
    {
        return $this->eventRecurrings;
    }

    public function addEventRecurring(EventRecurring $eventRecurring): static
    {
        if (!$this->eventRecurrings->contains($eventRecurring)) {
            $this->eventRecurrings->add($eventRecurring);
            $eventRecurring->addMonthDay($this);  // Mise à jour côté EventRecurring
        }

        return $this;
    }

    public function removeEventRecurring(EventRecurring $eventRecurring): static
    {
        if ($this->eventRecurrings->removeElement($eventRecurring)) {
            $eventRecurring->removeMonthDay($this);  // Mise à jour côté EventRecurring
        }

        return $this;
    }
}
