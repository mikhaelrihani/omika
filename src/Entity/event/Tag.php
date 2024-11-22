<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Index(name: "Tag_dateStatus_activeDay_idx", columns: ["date_status", "active_day"])]
#[ORM\Index(name: "Tag_dateStatus_day_idx", columns: ["date_status", "day"])]
class Tag extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['tag'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['tag'])]
    private string $section; // Section de l'événement

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['tag'])]
    private \DateTimeImmutable $day; // Jour concerné

    #[ORM\Column(length: 255)]
    #[Groups(['tag'])]
    private string $date_status; // Statut de la date (ex. "past", "active_day_range", "future")
   
    #[ORM\Column(nullable: true)]
    #[Groups(['tag'])]
    private ?int $active_day = null;

    /**
     * @var Collection<int, TagInfo>
     */
    #[ORM\OneToMany(targetEntity: TagInfo::class, mappedBy: 'tag', cascade:["persist", "remove"], orphanRemoval: true)]
    #[Groups(['tag'])]
    private Collection $tagInfos;

    #[ORM\Column(length: 255)]
    private ?string $side = null;

    /**
     * @var Collection<int, TagTask>
     */
    #[ORM\OneToMany(targetEntity: TagTask::class, mappedBy: 'tag', cascade:["persist", "remove"], orphanRemoval: true)]
    #[Groups(['tag'])]
    private Collection $tagTasks;


   
    public function __construct()
    {
        parent::__construct();
        $this->tagInfos = new ArrayCollection();
        $this->tagTasks = new ArrayCollection();
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

    /**
     * @return Collection<int, User>
     */

    /**
     * @return Collection<int, TagTask>
     */
    public function getTagTasks(): Collection
    {
        return $this->tagTasks;
    }

    public function addTagTask(TagTask $tagTask): static
    {
        if (!$this->tagTasks->contains($tagTask)) {
            $this->tagTasks->add($tagTask);
            $tagTask->setTag($this);
        }

        return $this;
    }

    public function removeTagTask(TagTask $tagTask): static
    {
        if ($this->tagTasks->removeElement($tagTask)) {
            // set the owning side to null (unless already changed)
            if ($tagTask->getTag() === $this) {
                $tagTask->setTag(null);
            }
        }

        return $this;
    }
   
  


}
