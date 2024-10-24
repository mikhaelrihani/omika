<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\EventRecurringRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRecurringRepository::class)]
class EventRecurring extends BaseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING)]
    private string $id; // Identifiant unique de la fréquence

    private ?\DateTimeImmutable $periode_start = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $periode_end = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $dates = null; // Jours du mois associé à la fréquence (1 à 31)

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 8,
        notInRangeMessage: 'Day must be between {{ min }} and {{ max }}. Use 8 for unlimited.'
    )]
    private ?array $weekDays = null; // Jours associé à la fréquence (1 = lundi, 7 = dimanche, 8 = illimité)

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 31,
        notInRangeMessage: 'Month day must be between {{ min }} and {{ max }}.'
    )]
    private ?array $monthDays = null;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'eventRecurring')]
    private Collection $events;

    public function __construct()
    {
        parent::__construct();
        $this->events = new ArrayCollection();
    }



    // Getters and Setters

    public function getId(): string
    {
        return $this->id;
    }

    public function getWeekDays(): ?array
    {
        return $this->weekDays;
    }

    public function setWeekDays(?array $weekDays): static
    {
        $this->weekDays = $weekDays;
        return $this;
    }

    public function getMonthDays(): ?array
    {
        return $this->monthDays;
    }

    public function setMonthDays(?array $monthDay): static
    {
        $this->monthDays = $monthDay;
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

    public function getDates(): ?array
    {
        return $this->dates;
    }

    public function setDates(?array $dates): static
    {
        $this->dates = $dates;

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setEventRecurring($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->events->removeElement($event)) {
            // set the owning side to null (unless already changed)
            if ($event->getEventRecurring() === $this) {
                $event->setEventRecurring(null);
            }
        }

        return $this;
    }

}
