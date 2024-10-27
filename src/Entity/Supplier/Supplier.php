<?php

namespace App\Entity\Supplier;

use App\Entity\BaseEntity;
use App\Entity\Event\Event;
use App\Entity\user\Business;
use App\Repository\Supplier\SupplierRepository;
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

  

    /**
     * @var Collection<int, Event>
     */
    #[ORM\ManyToMany(targetEntity: Event::class)]
    #[ORM\JoinTable(name: 'supplier_recurring_event_children')]

    private Collection $recurring_Event_children;

    /**
     * @var Collection<int, OrderDay>
     */
    #[ORM\ManyToMany(targetEntity: OrderDay::class, inversedBy: 'suppliers')]
    private Collection $orderDays;

    /**
     * @var Collection<int, DeliveryDay>
     */
    #[ORM\ManyToMany(targetEntity: DeliveryDay::class, inversedBy: 'suppliers')]
    private Collection $deliveryDays;

   

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->recurring_Event_children = new ArrayCollection();
        $this->orderDays = new ArrayCollection();
        $this->deliveryDays = new ArrayCollection();
   
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

  

    /**
     * @return Collection<int, Event>
     */
    public function getRecurringEventChildren(): Collection
    {
        return $this->recurring_Event_children;
    }

    public function addRecurringEventChild(Event $recurringEventChild): static
    {
        if (!$this->recurring_Event_children->contains($recurringEventChild)) {
            $this->recurring_Event_children->add($recurringEventChild);
        }

        return $this;
    }

    public function removeRecurringEventChild(Event $recurringEventChild): static
    {
        $this->recurring_Event_children->removeElement($recurringEventChild);

        return $this;
    }

    /**
     * @return Collection<int, OrderDay>
     */
    public function getOrderDays(): Collection
    {
        return $this->orderDays;
    }

    public function addOrderDay(OrderDay $orderDay): static
    {
        if (!$this->orderDays->contains($orderDay)) {
            $this->orderDays->add($orderDay);
        }

        return $this;
    }

    public function removeOrderDay(OrderDay $orderDay): static
    {
        $this->orderDays->removeElement($orderDay);

        return $this;
    }

    /**
     * @return Collection<int, DeliveryDay>
     */
    public function getDeliveryDays(): Collection
    {
        return $this->deliveryDays;
    }

    public function addDeliveryDay(DeliveryDay $deliveryDay): static
    {
        if (!$this->deliveryDays->contains($deliveryDay)) {
            $this->deliveryDays->add($deliveryDay);
        }

        return $this;
    }

    public function removeDeliveryDay(DeliveryDay $deliveryDay): static
    {
        $this->deliveryDays->removeElement($deliveryDay);

        return $this;
    }



   
}
