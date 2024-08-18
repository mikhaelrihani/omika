<?php

namespace App\Entity\inventory;

use App\Repository\inventory\ProductInventoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\product\product; 

#[ORM\Entity(repositoryClass: ProductInventoryRepository::class)]
class ProductInventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?product $product = null;

    #[ORM\ManyToOne(inversedBy: 'productInventories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?inventory $inventory = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $quantityBig = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $quantitySmall = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getInventory(): ?inventory
    {
        return $this->inventory;
    }

    public function setInventory(?inventory $inventory): static
    {
        $this->inventory = $inventory;

        return $this;
    }

    public function getQuantityBig(): ?string
    {
        return $this->quantityBig;
    }

    public function setQuantityBig(string $quantityBig): static
    {
        $this->quantityBig = $quantityBig;

        return $this;
    }

    public function getQuantitySmall(): ?string
    {
        return $this->quantitySmall;
    }

    public function setQuantitySmall(string $quantitySmall): static
    {
        $this->quantitySmall = $quantitySmall;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }
}
