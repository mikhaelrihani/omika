<?php

namespace App\Entity\Product;

use App\Entity\BaseEntity;
use App\Repository\Product\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Supplier\Supplier;
use App\Entity\Recipe\Unit;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Index(name: "kitchen_name_idx", columns: ["kitchen_name"])]
#[ORM\Index(name: "commercial_name_idx", columns: ["commercial_name"])]
#[ORM\Index(name: "kitchen_commercial_name_idx", columns: ["kitchen_name", "commercial_name"])]

class Product extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['supplier'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Kitchen Name should not be blank.")]
    #[Groups(['supplier'])]
    private ?string $kitchenName = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Commercial Name should not be blank.")]
    #[Groups(['supplier'])]
    private ?string $commercialName = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Slug should not be blank.")]
    private ?string $slug = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    #[Assert\NotBlank(message: "Price should not be blank.")]
    private ?string $price = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Conditionning should not be blank.")]
    private ?string $conditionning = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?unit $unit = null;

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank(message: "Supplier Favorite should not be blank.")]
    private ?bool $supplierFavorite = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\JoinColumn(name: 'supplier_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?supplier $supplier = null;

    #[ORM\OneToOne(targetEntity: Rupture::class, mappedBy: 'product')]
    private ?Rupture $rupture = null;


    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductType $type = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKitchenName(): ?string
    {
        return $this->kitchenName;
    }

    public function setKitchenName(string $kitchenName): static
    {
        $this->kitchenName = $kitchenName;

        return $this;
    }

    public function getCommercialName(): ?string
    {
        return $this->commercialName;
    }

    public function setCommercialName(string $commercialName): static
    {
        $this->commercialName = $commercialName;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }


    public function getConditionning(): ?string
    {
        return $this->conditionning;
    }

    public function setConditionning(string $conditionning): static
    {
        $this->conditionning = $conditionning;

        return $this;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function isSupplierFavorite(): ?bool
    {
        return $this->supplierFavorite;
    }

    public function setSupplierFavorite(bool $supplierFavorite): static
    {
        $this->supplierFavorite = $supplierFavorite;

        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

   
    public function getProductType(): ?ProductType
    {
        return $this->type;
    }

    public function setProductType(?ProductType $productType): static
    {
        $this->type = $productType;

        return $this;
    }

    public function getRupture(): ?Rupture
    {
        return $this->rupture;
    }
    
   
}
