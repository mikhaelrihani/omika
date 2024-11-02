<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Entity\user\User;
use App\Repository\Event\EventInfoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventInfoRepository::class)]
class EventInfo extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $sharedWith;

    #[ORM\OneToMany(mappedBy: 'eventInfo', targetEntity: EventSharedInfo::class, cascade: ['persist', 'remove'])]
    private Collection $eventSharedInfo;

    #[ORM\Column]
    private ?int $userReadInfoCount = null;

    #[ORM\Column]
    private ?int $sharedWithCount = null;

    #[ORM\Column]
    private ?bool $isFullyRead = null;

    public function __construct()
    {
        parent::__construct();
        $this->sharedWith = new ArrayCollection();
        $this->eventSharedInfo = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function addSharedWith(User $user): self
    {
        if (!$this->sharedWith->contains($user)) {
            $this->sharedWith->add($user);
        }
        return $this;
    }

    public function removeSharedWith(User $user): self
    {
        $this->sharedWith->removeElement($user);
        return $this;
    }

    public function getSharedWith(): Collection
    {
        return $this->sharedWith;
    }

    public function addEventSharedInfo(EventSharedInfo $eventSharedInfo): self
    {
        if (!$this->eventSharedInfo->contains($eventSharedInfo)) {
            $this->eventSharedInfo->add($eventSharedInfo);
            $eventSharedInfo->setEventInfo($this);
        }
        return $this;
    }

    public function removeEventSharedInfo(EventSharedInfo $eventSharedInfo): self
    {
        if ($this->eventSharedInfo->removeElement($eventSharedInfo)) {
            if ($eventSharedInfo->getEventInfo() === $this) {
                $eventSharedInfo->setEventInfo(null);
            }
        }
        return $this;
    }

    public function getEventSharedInfo(): Collection
    {
        return $this->eventSharedInfo;
    }

    public function getUserReadInfoCount(): ?int
    {
        return $this->userReadInfoCount;
    }

    public function setUserReadInfoCount(int $userReadInfoCount): static
    {
        $this->userReadInfoCount = $userReadInfoCount;
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

    public function isFullyRead(): ?bool
    {
        return $this->isFullyRead;
    }

    public function setIsFullyRead(bool $isFullyRead): static
    {
        $this->isFullyRead = $isFullyRead;
        return $this;
    }
}
