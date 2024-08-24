<?php

namespace App\Entity\inventory;
use App\Entity\BaseEntity;
use App\Repository\inventory\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
class Room extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255,nullable: false)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 255,nullable: false)]
    #[Assert\NotBlank]
    private ?string $locationDetails = null;

    /**
     * @var Collection<int, Inventory>
     */
    #[ORM\ManyToMany(targetEntity: Inventory::class, mappedBy: 'room')]
    private Collection $inventories;

    public function __construct()
    {
        $this->inventories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLocationDetails(): ?string
    {
        return $this->locationDetails;
    }

    public function setLocationDetails(string $locationDetails): static
    {
        $this->locationDetails = $locationDetails;

        return $this;
    }

    /**
     * @return Collection<int, Inventory>
     */
    public function getInventories(): Collection
    {
        return $this->inventories;
    }
   
}
