<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Entity\User\User;
use App\Repository\Event\EventRecurringRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRecurringRepository::class)]
#[ORM\Index(name: "EventRecurring_period_idx", columns: ["periodeStart", "periodeEnd"])]

class EventRecurring extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;


    #[ORM\Column(name: "periodeStart", type: 'datetime_immutable', nullable: false)]
    #[Assert\NotBlank(message: "La date de début est requise.")]
    #[Assert\Type("\DateTimeImmutable", message: "La date de début doit être de type DateTimeImmutable.")]
    private ?\DateTimeImmutable $periodeStart = null;

    #[ORM\Column(name: "periodeEnd", type: 'datetime_immutable', nullable: true)]
    #[Assert\Type("\DateTimeImmutable", message: "La date de fin doit être de type DateTimeImmutable.")]
    #[Assert\Expression(
        "this.getPeriodeEnd() === null || this.getPeriodeEnd() >= this.getPeriodeStart()",
        message: "La date de fin doit être postérieure ou égale à la date de début."
    )]
    private ?\DateTimeImmutable $periodeEnd = null;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'eventRecurring')]
    #[Assert\Valid]
    private Collection $events;

    /**
     * @var Collection<int, PeriodDate>
     */
    #[ORM\ManyToMany(targetEntity: PeriodDate::class, inversedBy: 'eventRecurrings', cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'event_recurring_period_date')]
    #[ORM\JoinColumn(name: 'event_recurring_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'period_date_id', referencedColumnName: 'id')]
    #[Assert\Valid]
    private Collection $periodDates;


    #[ORM\ManyToMany(targetEntity: WeekDay::class, inversedBy: 'eventRecurrings', cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'event_recurring_week_day')]
    #[ORM\JoinColumn(name: 'event_recurring_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'week_day_id', referencedColumnName: 'id')]
    private Collection $weekDays;


    #[ORM\ManyToMany(targetEntity: MonthDay::class, inversedBy: 'eventRecurrings', cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'event_recurring_month_day')]
    #[ORM\JoinColumn(name: 'event_recurring_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'month_day_id', referencedColumnName: 'id')]
    private Collection $monthDays;

    #[ORM\Column]
    private ?bool $isEveryday = false;

    #[ORM\Column(length: 255)]
    private ?string $recurrenceType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $updatedBy = null;

    public function __construct()
    {
        parent::__construct();
        $this->events = new ArrayCollection();
        $this->periodDates = new ArrayCollection();
        $this->weekDays = new ArrayCollection();
        $this->monthDays = new ArrayCollection();
    }

    // Getters and Setters

    public function getId(): int
    {
        return $this->id;
    }

    public function getPeriodeStart(): ?\DateTimeInterface
    {
        return $this->periodeStart;
    }

    public function setPeriodeStart(\DateTimeInterface $periodeStart): static
    {
        $this->periodeStart = $periodeStart;
        return $this;
    }

    public function getPeriodeEnd(): ?\DateTimeInterface
    {
        return $this->periodeEnd;
    }

    public function setPeriodeEnd(?\DateTimeInterface $periodeEnd): static
    {
        $this->periodeEnd = $periodeEnd;
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
            if ($event->getEventRecurring() === $this) {
                $event->setEventRecurring(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PeriodDate>
     */
    public function getPeriodDates(): Collection
    {
        return $this->periodDates;
    }

    /**
     * @return Collection<int, WeekDay>
     */
    public function getWeekDays(): Collection
    {
        return $this->weekDays;
    }

    public function addWeekDay(WeekDay $weekDay): static
    {
        if (!$this->weekDays->contains($weekDay)) {
            $this->weekDays->add($weekDay);
            $weekDay->addEventRecurring($this);  // Mise à jour de l'autre côté
        }

        return $this;
    }

    public function removeWeekDay(WeekDay $weekDay): static
    {
        if ($this->weekDays->removeElement($weekDay)) {
            $weekDay->removeEventRecurring($this);  // Mise à jour de l'autre côté
        }

        return $this;
    }


    /**
     * @return Collection<int, MonthDay>
     */
    public function getMonthDays(): Collection
    {
        return $this->monthDays;
    }

    public function addMonthDay(MonthDay $monthDay): static
    {
        if (!$this->monthDays->contains($monthDay)) {
            $this->monthDays->add($monthDay);
            $monthDay->addEventRecurring($this);  // Mise à jour de l'autre côté de la relation
        }

        return $this;
    }

    public function removeMonthDay(MonthDay $monthDay): static
    {
        if ($this->monthDays->removeElement($monthDay)) {
            $monthDay->removeEventRecurring($this);  // Mise à jour de l'autre côté de la relation
        }

        return $this;
    }

    public function addPeriodDate(PeriodDate $periodDate): static
    {
        if (!$this->periodDates->contains($periodDate)) {
            $this->periodDates->add($periodDate);
            $periodDate->addEventRecurring($this);  // Mise à jour de l'autre côté
        }

        return $this;
    }

    public function removePeriodDate(PeriodDate $periodDate): static
    {
        if ($this->periodDates->removeElement($periodDate)) {
            $periodDate->removeEventRecurring($this);  // Mise à jour de l'autre côté
        }

        return $this;
    }

    public function isEveryday(): ?bool
    {
        return $this->isEveryday;
    }

    public function setEveryday(bool $isEveryday): static
    {
        $this->isEveryday = $isEveryday;

        return $this;
    }

    public function resetRecurringDays(): void
    {
        $this->periodDates->clear();
        $this->weekDays->clear();
        $this->monthDays->clear();
    }

    public function getRecurrenceType(): ?string
    {
        return $this->recurrenceType;
    }

    public function setRecurrenceType(string $recurrenceType): static
    {
        $this->recurrenceType = $recurrenceType;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

}
