<?php

namespace App\Entity\inventory;

use App\Entity\BaseEntity;
use App\Entity\product\Product;
use App\Repository\inventory\InventoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InventoryRepository::class)]
class Inventory extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Status should not be blank.")]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Type should not be blank.")]
    private ?string $type = null;

    #[ORM\Column(length: 25, nullable: false)]
    #[Assert\NotBlank(message: "Month should not be blank.")]
    private ?string $month = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Author should not be blank.")]
    private ?string $author = null;

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank(message: "Year should not be blank.")]
    private ?int $year = null;

    #[ORM\Column(length: 255)]
    private ?string $pdfPath = null;

    #[ORM\Column(length: 255)]
    private ?string $excelPath = null;

    /**
     * @var Collection<int, Room>
     */
    #[ORM\ManyToMany(targetEntity: Room::class, inversedBy: 'inventories')]
    private Collection $room;

    /**
     * @var Collection<int, ProductInventory>
     */
    #[ORM\OneToMany(targetEntity: ProductInventory::class, mappedBy: 'inventory', orphanRemoval: true, cascade: ['persist'])]
    private Collection $productInventories;

    public function __construct()
    {
        $this->room = new ArrayCollection();
        $this->productInventories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getMonth(): ?string
    {
        return $this->month;
    }

    public function setMonth(string $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getYear(): ?string
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;

        return $this;
    }

    public function getExcelPath(): ?string
    {
        return $this->excelPath;
    }

    public function setExcelPath(string $excelPath): static
    {
        $this->excelPath = $excelPath;

        return $this;
    }

    /**
     * @return Collection<int, Room>
     */
    public function getRoom(): Collection
    {
        return $this->room;
    }

    public function addRoom(Room $room): static
    {
        if (!$this->room->contains($room)) {
            $this->room->add($room);
        }

        return $this;
    }

    public function removeRoom(Room $room): static
    {
        $this->room->removeElement($room);

        return $this;
    }

    /**
     * @return Collection<int, ProductInventory>
     */
    public function getProductInventories(): Collection
    {
        return $this->productInventories;
    }

    public function getProducts(): Collection
    {
        return $this->productInventories->map(function (ProductInventory $productInventory) {
            return $productInventory->getProduct();
        });
    }

    public function addProductInventory(Product $product, string $quantityBig, string $quantitySmall): static
    {
        // Chercher s'il existe déjà un ProductInventory avec ce produit
        foreach ($this->productInventories as $existingProductInventory) {
            if ($existingProductInventory->getProduct() === $product) {
                // Si un ProductInventory pour ce produit existe déjà, on peut modifier les quantités si nécessaire
                $existingProductInventory->setQuantityBig($quantityBig);
                $existingProductInventory->setQuantitySmall($quantitySmall);
                return $this;
            }
        }
    
        // Si aucun ProductInventory n'existe pour ce produit, on en crée un nouveau
        $productInventory = new ProductInventory();
        $productInventory->setInventory($this);
        $productInventory->setProduct($product);
        $productInventory->setQuantityBig($quantityBig);
        $productInventory->setQuantitySmall($quantitySmall);
    
        $this->productInventories->add($productInventory);
    
        return $this;
    }
    

    public function removeProductInventory(ProductInventory $productInventory): static
    {
        if ($this->productInventories->removeElement($productInventory)) {
            // set the owning side to null (unless already changed)
            if ($productInventory->getInventory() === $this) {
                $productInventory->setInventory(null);
            }
        }

        return $this;
    }
}
