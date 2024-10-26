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

    public function getId(): ?int
    {
        return $this->id;
    }
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\OneToMany(mappedBy: 'eventInfo', targetEntity: EventSharedInfo::class, cascade: ['persist', 'remove'])]

    private Collection $sharedWith;

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

    public function setFullyRead(bool $isFullyRead): static
    {
        $this->isFullyRead = $isFullyRead;

        return $this;
    }

}
