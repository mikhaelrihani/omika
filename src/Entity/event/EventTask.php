<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Entity\User\User;
use App\Repository\Event\EventTaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventTaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
class EventTask extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(mappedBy: 'task', cascade: ['remove'], orphanRemoval: true)]
    private ?Event $event = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Task status should not be blank.")]
    private ?string $taskStatus = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'user_task')]
    private Collection $sharedWith;

    /**
     * Count of users associated with this task.
     * This value is updated whenever users are added or removed from the sharedWith collection.
     *
     * @var int|null
     * @ORM\Column
     */
    #[ORM\Column]
    private ?int $sharedWithCount = null;

    #[ORM\Column]
    private ?bool $isPending = null;



    public function __construct()
    {
        parent::__construct();
        $this->sharedWith = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskStatus(): ?string
    {
        return $this->taskStatus;
    }

    public function setTaskStatus(string $task_status): static
    {
        $this->taskStatus = $task_status;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getSharedWith(): Collection
    {
        return $this->sharedWith;
    }
    /**
     * Adds a user to the sharedWith collection.
     * Synchronizes the sharedWithCount property after addition.
     *
     * @param User $user The user to associate with this task.
     * @return static
     */
    public function addsharedWith(User $user): static
    {
        if (!$this->sharedWith->contains($user)) {
            $this->sharedWith->add($user);
            $this->syncCounts();
        }

        return $this;
    }
    /**
     * Removes a user from the sharedWith collection.
     * Synchronizes the sharedWithCount property after removal.
     *
     * @param User $user The user to disassociate from this task.
     * @return static
     */
    public function removeSharedWith(User $user): static
    {
        $this->sharedWith->removeElement($user);
        $this->syncCounts();
        return $this;
    }

    public function getSharedWithCount(): ?int
    {
        return $this->sharedWithCount;
    }

    public function setSharedWithCount(int $sharedWithCount): static
    {
        $this->sharedWithCount = $sharedWithCount;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;
        return $this;
    }



    /**
     * Lifecycle callback triggered before the entity is removed.
     * Clears the sharedWith collection to clean up relations in the database.
     * Synchronizes the sharedWithCount property to maintain consistency.
     *
     * @ORM\PreRemove
     */
    #[ORM\PreRemove]
    public function cleanupRelations(): void
    {
        if ($this->sharedWith !== null) {
            $this->sharedWith->clear();
            $this->syncCounts(); // Maintenir les compteurs cohérents
        }
    }
    public function syncCounts(): void
    {
        $this->sharedWithCount = $this->sharedWith->count();
    }

    public function isPending(): ?bool
    {
        return $this->isPending;
    }

    public function setPending(bool $isPending): static
    {
        $this->isPending = $isPending;

        return $this;
    }


}
