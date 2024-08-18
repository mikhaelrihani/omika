<?php

namespace App\Entity\inventory;

use App\Repository\inventory\InventoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryRepository::class)]
class Inventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(length: 25)]
    private ?string $month = null;

    #[ORM\Column(length: 255)]
    private ?string $author = null;

    #[ORM\Column(length: 255)]
    private ?string $year = null;

    #[ORM\Column(length: 255)]
    private ?string $pdfPath = null;

    #[ORM\Column(length: 255)]
    private ?string $excelPath = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    /**
     * @var Collection<int, room>
     */
    #[ORM\ManyToMany(targetEntity: room::class, inversedBy: 'inventories')]
    private Collection $room;

    /**
     * @var Collection<int, ProductInventory>
     */
    #[ORM\OneToMany(targetEntity: ProductInventory::class, mappedBy: 'inventory', orphanRemoval: true)]
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

    public function setYear(string $year): static
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

    /**
     * @return Collection<int, room>
     */
    public function getRoom(): Collection
    {
        return $this->room;
    }

    public function addRoom(room $room): static
    {
        if (!$this->room->contains($room)) {
            $this->room->add($room);
        }

        return $this;
    }

    public function removeRoom(room $room): static
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

    public function addProductInventory(ProductInventory $productInventory): static
    {
        if (!$this->productInventories->contains($productInventory)) {
            $this->productInventories->add($productInventory);
            $productInventory->setInventory($this);
        }

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
