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

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Room Name should not be blank.")]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Location Details should not be blank.")]
    private ?string $locationDetails = null;

    /**
     * @var Collection<int, Inventory>
     */
    #[ORM\ManyToMany(targetEntity: Inventory::class, mappedBy: 'room')]
    private Collection $inventories;

    /**
     * @ORM\OneToMany(targetEntity="RoomProduct", mappedBy="room")
     */
    #[ORM\OneToMany(targetEntity: RoomProduct::class, mappedBy: 'room')]
    private Collection $roomProducts;

    public function __construct()
    {
        $this->inventories = new ArrayCollection();
        $this->roomProducts = new ArrayCollection();
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

    /**
     * @return Collection<int, RoomProduct>
     */
    public function getRoomProducts(): Collection
    {
        return $this->roomProducts;
    }
    public function addRoomProduct(RoomProduct $roomProduct): static
    {

        if (!$this->roomProducts->contains($roomProduct)) {
            $this->roomProducts->add($roomProduct);
        }

        return $this;
    }



    /**
     * Get all products associated with the room via the RoomProduct pivot table
     *
     * @return Collection
     */
    public function getProducts(): Collection
    {
        return $this->roomProducts->map(function (RoomProduct $roomProduct) {
            return $roomProduct->getProduct();
        });
    }
}
