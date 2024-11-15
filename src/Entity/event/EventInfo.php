<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
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

    #[ORM\Column]
    private ?int $userReadInfoCount = null;

    #[ORM\Column]
    private ?int $sharedWithCount = null;

    #[ORM\Column]
    private ?bool $isFullyRead = null;

    /**
     * @var Collection<int, UserInfo>
     */
    #[ORM\OneToMany(targetEntity: UserInfo::class, mappedBy: 'eventInfo', orphanRemoval: true)]
    private Collection $sharedWith;

    public function __construct()
    {
        parent::__construct();
        $this->sharedWith = new ArrayCollection(); 
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return Collection<int, UserInfo>
     */
    public function getSharedWith(): Collection
    {
        return $this->sharedWith;
    }

    public function addSharedWith(UserInfo $sharedWith): static
    {
        if (!$this->sharedWith->contains($sharedWith)) {
            $this->sharedWith->add($sharedWith);
            $sharedWith->setEventInfo($this);
        }

        return $this;
    }

    public function removeSharedWith(UserInfo $sharedWith): static
    {
        if ($this->sharedWith->removeElement($sharedWith)) {
            // set the owning side to null (unless already changed)
            if ($sharedWith->getEventInfo() === $this) {
                $sharedWith->setEventInfo(null);
            }
        }

        return $this;
    }

  
}