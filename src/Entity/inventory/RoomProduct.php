<?php

namespace App\Entity\inventory;

use App\Entity\BaseEntity;
use App\Repository\inventory\RoomProductRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\product\product; 
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoomProductRepository::class)]
class RoomProduct extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?room $room = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?product $product = null;

    #[ORM\Column]
    private ?int $roomShelf = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoom(): ?room
    {
        return $this->room;
    }

    public function setRoom(?room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getProduct(): ?product
    {
        return $this->product;
    }

    public function setProduct(?product $product): static
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
