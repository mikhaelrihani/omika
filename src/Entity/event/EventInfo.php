<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\EventInfoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents additional information related to an event, such as shared users and read status.
 *
 * @package App\Entity\Event
 */
#[ORM\Entity(repositoryClass: EventInfoRepository::class)]
class EventInfo extends BaseEntity
{
    /**
     * The unique identifier for the EventInfo entity.
     *
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The event associated with this EventInfo.
     *
     * @var Event|null
     */
    #[ORM\OneToOne(targetEntity: Event::class, mappedBy: 'eventInfo', cascade: ['remove'], orphanRemoval: true)]
    private ?Event $event = null;

    /**
     * The count of users who have read the event information.
     *
     * @var int|null
     */
    #[ORM\Column]
    private ?int $userReadInfoCount = null;

    /**
     * The total count of users with whom the event information is shared.
     *
     * @var int|null
     */
    #[ORM\Column]
    private ?int $sharedWithCount = null;

    /**
     * Whether the event information has been fully read by all users.
     *
     * @var bool|null
     */
    #[ORM\Column]
    private ?bool $isFullyRead = null;

    /**
     * A collection of UserInfo objects representing users associated with the event information.
     *
     * @var Collection<int, UserInfo>
     */
    #[ORM\OneToMany(targetEntity: UserInfo::class, mappedBy: 'eventInfo', orphanRemoval: true)]
    private Collection $sharedWith;

    /**
     * Initializes the EventInfo entity and its collections.
     */
    public function __construct()
    {
        parent::__construct();
        $this->sharedWith = new ArrayCollection();
    }

    /**
     * Gets the unique identifier for the EventInfo entity.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Gets the count of users who have read the event information.
     *
     * @return int|null
     */
    public function getUserReadInfoCount(): ?int
    {
        return $this->userReadInfoCount;
    }

    /**
     * Sets the count of users who have read the event information.
     *
     * @param int $userReadInfoCount
     * @return static
     */
    public function setUserReadInfoCount(int $userReadInfoCount): static
    {
        $this->userReadInfoCount = $userReadInfoCount;
        return $this;
    }

    /**
     * Gets the total count of users with whom the event information is shared.
     *
     * @return int|null
     */
    public function getSharedWithCount(): ?int
    {
        return $this->sharedWithCount;
    }

    /**
     * Sets the total count of users with whom the event information is shared.
     *
     * @param int $sharedWithCount
     * @return static
     */
    public function setSharedWithCount(int $sharedWithCount): static
    {
        $this->sharedWithCount = $sharedWithCount;
        return $this;
    }

    /**
     * Determines whether the event information has been fully read by all users.
     *
     * @return bool|null
     */
    public function isFullyRead(): ?bool
    {
        return $this->isFullyRead;
    }

    /**
     * Sets whether the event information has been fully read by all users.
     *
     * @param bool $isFullyRead
     * @return static
     */
    public function setIsFullyRead(bool $isFullyRead): static
    {
        $this->isFullyRead = $isFullyRead;
        return $this;
    }

    /**
     * Gets the collection of UserInfo objects associated with the event information.
     *
     * @return Collection<int, UserInfo>
     */
    public function getSharedWith(): Collection
    {
        return $this->sharedWith;
    }

    /**
     * Adds a UserInfo object to the shared collection.
     * Synchronizes the counts and relationships.
     *
     * @param UserInfo $sharedWith
     * @return static
     */
    public function addSharedWith(UserInfo $sharedWith): static
    {
        if (!$this->sharedWith->contains($sharedWith)) {
            $this->sharedWith->add($sharedWith);
            $sharedWith->setEventInfo($this);
            $this->syncCounts();
        }

        return $this;
    }

    /**
     * Removes a UserInfo object from the shared collection.
     * Synchronizes the counts and relationships.
     *
     * @param UserInfo $sharedWith
     * @return static
     */
    public function removeSharedWith(UserInfo $sharedWith): static
    {
        if ($this->sharedWith->removeElement($sharedWith)) {
            if ($sharedWith->getEventInfo() === $this) {
                $sharedWith->setEventInfo(null);
            }
            $this->syncCounts();
        }

        return $this;
    }

    /**
     * Synchronizes all related counters based on the sharedWith collection.
     */
    public function syncCounts(): void
    {
        $this->sharedWithCount = $this->sharedWith->count();
        $this->userReadInfoCount = $this->sharedWith->filter(fn(UserInfo $userInfo) => $userInfo->isRead())->count();
        $this->isFullyRead = $this->userReadInfoCount === $this->sharedWithCount;
    }

    /**
     * Gets the event associated with this EventInfo.
     *
     * @return Event|null
     */
    public function getEvent(): ?Event
    {
        return $this->event;
    }
}
