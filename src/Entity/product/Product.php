<?php

namespace App\Entity\Product;

use App\Entity\BaseEntity;
use App\Entity\Inventory\Room;
use App\Entity\Inventory\RoomProduct;
use App\Repository\Product\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Supplier\Supplier;
use App\Entity\Recipe\Unit;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Cascade;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Index(name: "kitchen_name_idx", columns: ["kitchen_name"])]
#[ORM\Index(name: "commercial_name_idx", columns: ["commercial_name"])]
#[ORM\Index(name: "kitchen_commercial_name_idx", columns: ["kitchen_name", "commercial_name"])]

class Product extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['supplier', 'product'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Kitchen Name should not be blank.")]
    #[Groups(['supplier', 'product'])]
    private ?string $kitchenName = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Commercial Name should not be blank.")]
    #[Groups(['supplier', 'product'])]
    private ?string $commercialName = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Slug should not be blank.")]
    #[Groups(['product'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    #[Assert\NotBlank(message: "Price should not be blank.")]
    #[Groups(['product'])]
    private ?string $price = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Conditionning should not be blank.")]
    #[Groups(['product'])]
    private ?string $conditionning = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product'])]
    private ?unit $unit = null;

    #[ORM\Column(nullable: false)]
    #[Groups(['product'])]
    private ?bool $supplierFavorite = false;

    #[ORM\ManyToOne(targetEntity: Supplier::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\JoinColumn(name: 'supplier_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['product'])]
    private ?supplier $supplier = null;

    #[ORM\OneToOne(targetEntity: Rupture::class, mappedBy: 'product')]
    #[Groups(['product'])]
    private ?Rupture $rupture = null;


    #[ORM\ManyToOne(targetEntity: ProductType::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product'])]
    private ?ProductType $type = null;

    #[ORM\OneToMany(targetEntity: RoomProduct::class, cascade: ['persist', 'remove'], mappedBy: 'product')]
    #[Groups(['product'])]
    private Collection $roomProducts;


    public function __construct()
    {
        $this->roomProducts = new ArrayCollection();
    }

    public function getRoomProducts(): Collection
    {
        return $this->roomProducts;
    }

    public function addRoomProduct(RoomProduct $roomProduct): static
    {
        if (!$this->roomProducts->contains($roomProduct)) {
            $this->roomProducts[] = $roomProduct;
            $roomProduct->setProduct($this);
        }

        return $this;
    }

    public function removeRoomProduct(RoomProduct $roomProduct): static
    {
        if ($this->roomProducts->removeElement($roomProduct)) {
            if ($roomProduct->getProduct() === $this) {
                $roomProduct->setProduct(null);
            }
        }

        return $this;
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

    public function getType(): ?ProductType
    {
        return $this->type;
    }


    public function setType(?ProductType $productType): static
    {
        $this->type = $productType;

        return $this;
    }

    public function getRupture(): ?Rupture
    {
        return $this->rupture;
    }

    public function setRupture(?Rupture $rupture): static
    {
        $this->rupture = $rupture;

        return $this;
    }

}
