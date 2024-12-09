<?php

namespace App\Entity\Inventory;

use App\Entity\BaseEntity;
use App\Repository\Inventory\RoomProductRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Product\Product;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RoomProductRepository::class)]
class RoomProduct extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'roomProducts', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['product'])]
    private ?Room $room = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'roomProducts', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['product'])]
    private ?int $roomShelf = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getRoomShelf(): ?int
    {
        return $this->roomShelf;
    }

    public function setRoomShelf(int $roomShelf): static
    {
        $this->roomShelf = $roomShelf;

        return $this;
    }
}
