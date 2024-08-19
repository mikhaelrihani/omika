<?php

namespace App\Entity\product;

use App\Repository\product\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\supplier\Supplier; 
use App\Entity\recipe\Unit; 
use App\Entity\recipe\Recipe; 
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $kitchenName = null;

    #[ORM\Column(length: 255)]
    private ?string $commercialName = null;

    #[ORM\Column(length: 50)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $conditionning = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?unit $unit = null;

    #[ORM\Column]
    private ?bool $supplierFavorite = null;

    #[ORM\OneToOne(inversedBy: 'products', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?supplier $supplier = null;

    /**
     * @var Collection<int, Recipe>
     */
    #[ORM\ManyToMany(targetEntity: Recipe::class, mappedBy: 'product')]
    private Collection $recipes;

    public function __construct()
    {
        $this->recipes = new ArrayCollection();
    }

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getUnit(): ?unit
    {
        return $this->unit;
    }

    public function setUnit(?unit $unit): static
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

    public function getSupplier(): ?supplier
    {
        return $this->supplier;
    }

    public function setSupplier(supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    /**
     * @return Collection<int, Recipe>
     */
    public function getRecipes(): Collection
    {
        return $this->recipes;
    }

    public function addRecipe(Recipe $recipe): static
    {
        if (!$this->recipes->contains($recipe)) {
            $this->recipes->add($recipe);
            $recipe->addProduct($this);
        }

        return $this;
    }

    public function removeRecipe(Recipe $recipe): static
    {
        if ($this->recipes->removeElement($recipe)) {
            $recipe->removeProduct($this);
        }

        return $this;
    }
}
