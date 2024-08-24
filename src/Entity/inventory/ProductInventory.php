<?php

namespace App\Entity\inventory;

use App\Entity\BaseEntity;
use App\Repository\inventory\ProductInventoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\product\product; 
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductInventoryRepository::class)]
class ProductInventory extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity:Inventory::class, inversedBy: 'productInventories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Inventory $inventory = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $quantityBig = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $quantitySmall = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getInventory(): ?Inventory
    {
        return $this->inventory;
    }

    public function setInventory(?Inventory $inventory): static
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
}
