<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\PeriodDateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PeriodDateRepository::class)]
class PeriodDate extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    /**
     * @var Collection<int, EventRecurring>
     */
    #[ORM\ManyToMany(targetEntity: EventRecurring::class, mappedBy: 'periodDates')]
    private Collection $eventRecurrings;

    public function __construct()
    {
        $this->eventRecurrings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return Collection<int, EventRecurring>
     */
    public function getEventRecurrings(): Collection
    {
        return $this->eventRecurrings;
    }

    public function addEventRecurring(EventRecurring $eventRecurring): static
    {
        if (!$this->eventRecurrings->contains($eventRecurring)) {
            $this->eventRecurrings->add($eventRecurring);
            $eventRecurring->addPeriodDate($this);  // Synchronisation côté EventRecurring
        }

        return $this;
    }

    public function removeEventRecurring(EventRecurring $eventRecurring): static
    {
        if ($this->eventRecurrings->removeElement($eventRecurring)) {
            $eventRecurring->removePeriodDate($this);  // Synchronisation côté EventRecurring
        }

        return $this;
    }
}
