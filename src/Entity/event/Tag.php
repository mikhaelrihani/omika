<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
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

    #[ORM\Column(type: Types::INTEGER)]
    private int $task_count = 0; // Compte des tâches actives

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $allUserRead = false;

    #[ORM\Column(nullable: true)]
    private ?array $active_day_range = null;

    /**
     * @var Collection<int, TagInfo>
     */
    #[ORM\OneToMany(targetEntity: TagInfo::class, mappedBy: 'tag', orphanRemoval: true)]
    private Collection $tagInfos;

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

    public function getTaskCount(): int
    {
        return $this->task_count;
    }

    public function setTaskCount(int $task_count): static
    {
        $this->task_count = $task_count;
        return $this;
    }

    public function isAllUserRead(): bool
    {
        return $this->allUserRead;
    }

    public function setAllUserRead(bool $allUserRead): static
    {
        $this->allUserRead = $allUserRead;
        return $this;
    }

    public function getActiveDayRange(): ?array
    {
        return $this->active_day_range;
    }

    public function setActiveDayRange(?array $active_day_range): static
    {
        $this->active_day_range = $active_day_range;

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

   
   
}
