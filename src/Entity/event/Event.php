<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Entity\Product\Supplier;
use App\Entity\User\User;
use App\Repository\Event\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Index(name: "Event_dateStatus_activeDay_idx", columns: ["date_status", "active_day"])]
#[ORM\Index(name: "Event_dateStatus_dueDate_idx", columns: ["date_status", "due_date"])]
class Event extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

  
    #[ORM\Column(type: 'boolean', nullable: false)]
    private ?bool $isRecurring = false;

    #[ORM\ManyToOne(inversedBy: 'events')]
    private ?EventRecurring $eventRecurring = null;

    #[ORM\OneToOne(targetEntity: EventTask::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?EventTask $task = null;

    #[ORM\OneToOne(targetEntity:EventInfo::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?EventInfo $info = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: false)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Date status should not be blank.")]
    private ?string $date_status = null;

    #[ORM\Column(nullable: true)]
    private ?int $active_day = null;



    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Side should not be blank.")]
    private ?string $side = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Type should not be blank.")]
    private ?string $type = null;

    #[ORM\ManyToOne(targetEntity: Section::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Section $section = null;



    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank(message: "Title should not be blank.")]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank(message: "Description should not be blank.")]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "CreatedBy should not be blank.")]
    private ?string $createdBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $updatedBy = null;

    #[ORM\Column(type: 'boolean', nullable: false)]
    #[Assert\NotNull]
    private ?bool $isImportant = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'favoriteEvents')]
    #[ORM\JoinTable(name: 'user_favoriteEvents')]
    private Collection $favoritedBy;

   
    public function __construct()
    {
        parent::__construct();
        $this->favoritedBy = new ArrayCollection();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isRecurring(): ?bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function getEventRecurring(): ?EventRecurring
    {
        return $this->eventRecurring;
    }

    public function setEventRecurring(?EventRecurring $eventRecurring): static
    {
        $this->eventRecurring = $eventRecurring;
        return $this;
    }

    public function getTask(): ?EventTask
    {
        return $this->task;
    }

    public function setTask(EventTask $task): static
    {
        $this->task = $task;
        return $this;
    }

    public function getEventInfo(): ?EventInfo
    {
        return $this->info;
    }

    public function setEventInfo(?EventInfo $info): static
    {
        $this->info = $info;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getDateStatus(): ?string
    {
        return $this->date_status;
    }

    public function setDateStatus(string $date_status): static
    {
        $this->date_status = $date_status;
        return $this;
    }

    public function getActiveDay(): ?int
    {
        return $this->active_day;
    }

    public function setActiveDay(?int $active_day): static
    {
        $this->active_day = $active_day;
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

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(Section $section): static
    {
        $this->section = $section;
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

    public function isImportant(): ?bool
    {
        return $this->isImportant;
    }

    public function setIsImportant(bool $isImportant): static
    {
        $this->isImportant = $isImportant;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getFavoritedBy(): Collection
    {
        return $this->favoritedBy;
    }

    public function addFavoritedBy(User $favoritedBy): static
    {
        if (!$this->favoritedBy->contains($favoritedBy)) {
            $this->favoritedBy->add($favoritedBy);
        }
        return $this;
    }

    public function removeFavoritedBy(User $favoritedBy): static
    {
        $this->favoritedBy->removeElement($favoritedBy);
        return $this;
    }

}
