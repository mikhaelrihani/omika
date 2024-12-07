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
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: EventRecurringRepository::class)]
#[ORM\Index(name: "EventRecurring_period_idx", columns: ["periodeStart", "periodeEnd"])]

class EventRecurring extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['eventRecurring',"supplier"])]
    private ?int $id = null;


    #[ORM\Column(name: "periodeStart", type: 'datetime_immutable', nullable: false)]
    #[Assert\NotBlank(message: "La date de début est requise.")]
    #[Assert\Type("\DateTimeImmutable", message: "La date de début doit être de type DateTimeImmutable.")]
    #[Groups(['eventRecurring'])]
    private ?\DateTimeImmutable $periodeStart = null;

    #[ORM\Column(name: "periodeEnd", type: 'datetime_immutable', nullable: true)]
    #[Assert\Type("\DateTimeImmutable", message: "La date de fin doit être de type DateTimeImmutable.")]
    #[Assert\Expression(
        "this.getPeriodeEnd() === null || this.getPeriodeEnd() >= this.getPeriodeStart()",
        message: "La date de fin doit être postérieure ou égale à la date de début."
    )]
    #[Groups(['eventRecurring'])]
    private ?\DateTimeImmutable $periodeEnd = null;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'eventRecurring', cascade: ['remove'])]
    #[Assert\Valid]
    #[Groups(['eventRecurring'])]
    private Collection $events;

    /**
     * @var Collection<int, PeriodDate>
     */
    #[ORM\ManyToMany(targetEntity: PeriodDate::class, inversedBy: 'eventRecurrings', cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'event_recurring_period_date')]
    #[ORM\JoinColumn(name: 'event_recurring_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'period_date_id', referencedColumnName: 'id')]
    #[Assert\Valid]
    #[Groups(['eventRecurring'])]
    private Collection $periodDates;


    #[ORM\ManyToMany(targetEntity: WeekDay::class, inversedBy: 'eventRecurrings', cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'event_recurring_week_day')]
    #[ORM\JoinColumn(name: 'event_recurring_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'week_day_id', referencedColumnName: 'id')]
    #[Groups(['eventRecurring'])]
    private Collection $weekDays;


    #[ORM\ManyToMany(targetEntity: MonthDay::class, inversedBy: 'eventRecurrings', cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'event_recurring_month_day')]
    #[ORM\JoinColumn(name: 'event_recurring_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'month_day_id', referencedColumnName: 'id')]
    #[Groups(['eventRecurring'])]
    private Collection $monthDays;

    #[ORM\Column]
    #[Groups(['eventRecurring'])]
    private ?bool $isEveryday = false;

    #[ORM\Column(length: 255)]
    #[Groups(['eventRecurring',"supplier"])]
    private ?string $recurrenceType = null;

    #[ORM\Column(length: 255)]
    #[Groups(['eventRecurring'])]
    private ?string $createdBy = null;

    #[ORM\Column(length: 255)]
    #[Groups(['eventRecurring'])]
    private ?string $updatedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['eventRecurring'])]
    private ?Section $section = null;

    #[ORM\Column(length: 255)]
    #[Groups(['eventRecurring'])]
    private ?string $side = null;

    #[ORM\Column(length: 255)]
    #[Groups(['eventRecurring'])]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Groups(['eventRecurring'])]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Groups(['eventRecurring'])]
    private ?string $description = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[Groups(['eventRecurring'])]
    private Collection $sharedWith;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->periodDates = new ArrayCollection();
        $this->weekDays = new ArrayCollection();
        $this->monthDays = new ArrayCollection();
        $this->sharedWith = new ArrayCollection();
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

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): static
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(?Section $section): static
    {
        $this->section = $section;

        return $this;
    }

    public function getSide(): ?string
    {
        return $this->side;
    }

    public function setSide(string $side): static
    {
        $this->side = $side;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getSharedWith(): Collection
    {
        return $this->sharedWith;
    }

    public function addSharedWith(User $sharedWith): static
    {
        if (!$this->sharedWith->contains($sharedWith)) {
            $this->sharedWith->add($sharedWith);
        }

        return $this;
    }

    public function removeSharedWith(User $sharedWith): static
    {
        $this->sharedWith->removeElement($sharedWith);

        return $this;
    }

}
