<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\WeekDayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WeekDayRepository::class)]
class WeekDay extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: "Le jour de la semaine est requis.")]
    #[Assert\Range(min: 1, max: 7, notInRangeMessage: "Le jour de la semaine doit être entre 1 (lundi) et 7 (dimanche).")]
    private int $day;  // Champ pour le jour de la semaine

    /**
     * @var Collection<int, EventRecurring>
     */
    #[ORM\ManyToMany(targetEntity: EventRecurring::class, mappedBy: 'weekDays')]
    private Collection $eventRecurrings;

    public function __construct()
    {
        $this->eventRecurrings = new ArrayCollection();
    }

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
            $eventRecurring->addWeekDay($this);
        }

        return $this;
    }

    public function removeEventRecurring(EventRecurring $eventRecurring): static
    {
        if ($this->eventRecurrings->removeElement($eventRecurring)) {
            $eventRecurring->removeWeekDay($this);
        }

        return $this;
    }
}
