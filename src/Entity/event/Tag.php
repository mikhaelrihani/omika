<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Index(name: "Tag_dateStatus_activeDay_idx", columns: ["date_status", "active_day"])]
#[ORM\Index(name: "Tag_dateStatus_day_idx", columns: ["date_status", "day"])]
class Tag extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $section; // Section de l'événement

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeImmutable $day; // Jour concerné

    #[ORM\Column(length: 255)]
    private string $date_status; // Statut de la date (ex. "past", "active_day_range", "future")

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $task_count = null; // Compte des tâches actives, peut être null


    #[ORM\Column(nullable: true)]
    private ?int $active_day = null;

    /**
     * @var Collection<int, TagInfo>
     */
    #[ORM\OneToMany(targetEntity: TagInfo::class, mappedBy: 'tag', orphanRemoval: true)]
    private Collection $tagInfos;

    #[ORM\Column(length: 255)]
    private ?string $side = null;

    public function __construct()
    {
        parent::__construct();
        $this->tagInfos = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSection(): string
    {
        return $this->section;
    }

    public function setSection(string $section): static
    {
        $this->section = $section;
        return $this;
    }

    public function getDay(): \DateTimeInterface
    {
        return $this->day;
    }

    public function setDay(\DateTimeInterface $day): static
    {
        $this->day = $day;
        return $this;
    }

    public function getDateStatus(): string
    {
        return $this->date_status;
    }

    public function setDateStatus(string $date_status): static
    {
        $this->date_status = $date_status;
        return $this;
    }

    public function getTaskCount(): int|null
    {
        return $this->task_count;
    }

    public function setTaskCount(?int $task_count): static
    {
        $this->task_count = $task_count;
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

    /**
     * @return Collection<int, TagInfo>
     */
    public function getTagInfos(): Collection
    {
        return $this->tagInfos;
    }

    public function addTagInfo(TagInfo $tagInfo): static
    {
        if (!$this->tagInfos->contains($tagInfo)) {
            $this->tagInfos->add($tagInfo);
            $tagInfo->setTag($this);
        }

        return $this;
    }

    public function removeTagInfo(TagInfo $tagInfo): static
    {
        if ($this->tagInfos->removeElement($tagInfo)) {
            // set the owning side to null (unless already changed)
            if ($tagInfo->getTag() === $this) {
                $tagInfo->setTag(null);
            }
        }

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



}
