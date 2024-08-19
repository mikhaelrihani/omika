<?php

namespace App\Entity\supplier;

use App\Repository\supplier\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\product\Product; 
use App\Entity\order\Order; 
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
class Supplier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

   

    #[ORM\Column(length: 1000)]
    private ?string $logistic = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $habits = null;

    #[ORM\Column(type: Types::JSON)]
    private array $orderDays = [];

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $goodToKnow = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\OneToOne(mappedBy: 'supplier', cascade: ['persist', 'remove'])]
    private ?Product $products = null;

    /**
     * @var Collection<int, SupplierStaff>
     */
    #[ORM\OneToMany(targetEntity: SupplierStaff::class, mappedBy: 'supplier', orphanRemoval: true)]
    private Collection $supplierStaff;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'supplier', orphanRemoval: true)]
    private Collection $orders;

    public function __construct()
    {
        $this->supplierStaff = new ArrayCollection();
        $this->orders = new ArrayCollection();
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

    

    public function getLogistic(): ?string
    {
        return $this->logistic;
    }

    public function setLogistic(string $logistic): static
    {
        $this->logistic = $logistic;

        return $this;
    }

    public function getHabits(): ?string
    {
        return $this->habits;
    }

    public function setHabits(?string $habits): static
    {
        $this->habits = $habits;

        return $this;
    }

    public function getOrderDays(): array
    {
        return $this->orderDays;
    }

    public function setOrderDays(array $orderDays): static
    {
        $this->orderDays = $orderDays;

        return $this;
    }

    public function getGoodToKnow(): ?string
    {
        return $this->goodToKnow;
    }

    public function setGoodToKnow(?string $goodToKnow): static
    {
        $this->goodToKnow = $goodToKnow;

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

    public function getProducts(): ?Product
    {
        return $this->products;
    }

    public function setProducts(Product $products): static
    {
        // set the owning side of the relation if necessary
        if ($products->getSupplier() !== $this) {
            $products->setSupplier($this);
        }

        $this->products = $products;

        return $this;
    }

    /**
     * @return Collection<int, SupplierStaff>
     */
    public function getSupplierStaff(): Collection
    {
        return $this->supplierStaff;
    }

    public function addSupplierStaff(SupplierStaff $supplierStaff): static
    {
        if (!$this->supplierStaff->contains($supplierStaff)) {
            $this->supplierStaff->add($supplierStaff);
            $supplierStaff->setSupplier($this);
        }

        return $this;
    }

    public function removeSupplierStaff(SupplierStaff $supplierStaff): static
    {
        if ($this->supplierStaff->removeElement($supplierStaff)) {
            // set the owning side to null (unless already changed)
            if ($supplierStaff->getSupplier() === $this) {
                $supplierStaff->setSupplier(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setSupplier($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getSupplier() === $this) {
                $order->setSupplier(null);
            }
        }

        return $this;
    }
}
