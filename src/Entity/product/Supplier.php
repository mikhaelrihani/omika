<?php

namespace App\Entity\Product;

use App\Entity\BaseEntity;
use App\Entity\user\Business;
use App\Repository\product\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\product\Product;
use App\Entity\order\Order;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
class Supplier extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Business $business = null;


    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Supplier Logistic should not be blank.")]
    private ?string $logistic = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $habits = null;

    #[ORM\Column(type: Types::JSON, nullable: false)]
    #[Assert\NotBlank(message: "Order Days should not be blank.")]
    private array $orderDays = [];

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $goodToKnow = null;



    /**
     * @ORM\OneToMany(targetEntity="App\Entity\product\Product", mappedBy="supplier")
     */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'supplier', orphanRemoval: true)]
    private Collection $products;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'supplier', orphanRemoval: true)]
    private Collection $orders;

    #[ORM\Column]
    private array $deliveryDays = [];

    #[ORM\Column(nullable: true, type: 'json')]
    private ?array $recuring_events = null;


    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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


    /**
     * @return Collection|Product[]
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products[] = $product;
            $product->setSupplier($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): self
    {
        $this->products->removeElement($product);

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

    public function getBusiness(): ?Business
    {
        return $this->business;
    }

    public function setBusiness(Business $business): static
    {
        $this->business = $business;

        return $this;
    }

    public function getDeliveryDays(): array
    {
        return $this->deliveryDays;
    }

    public function setDeliveryDays(array $deliveryDays): static
    {
        $this->deliveryDays = $deliveryDays;

        return $this;
    }

    public function getRecuringEvents(): ?array
    {
        return $this->recuring_events;
    }

    public function setRecuringEvents(?array $recuring_events): static
    {
        $this->recuring_events = $recuring_events;

        return $this;
    }
}
