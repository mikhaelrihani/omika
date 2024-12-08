<?php

namespace App\Entity\Supplier;

use App\Entity\BaseEntity;
use App\Entity\Event\EventRecurring;
use App\Entity\User\Business;
use App\Repository\Supplier\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Product\Product;
use App\Entity\Order\Order;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
class Supplier extends BaseEntity
{


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['supplier'])]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['supplier'])]
    private ?Business $business = null;


    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Supplier Logistic should not be blank.")]
    #[Groups(['supplier'])]
    private ?string $logistic = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Groups(['supplier'])]
    private ?string $habits = null;


    #[ORM\Column(length: 1000, nullable: true)]
    #[Groups(['supplier'])]
    private ?string $goodToKnow = null;



    /**
     * @ORM\OneToMany(targetEntity="App\Entity\product\Product", mappedBy="supplier")
     */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'supplier', orphanRemoval: true)]
    #[Groups(['supplier'])]
    private Collection $products;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'supplier', orphanRemoval: true)]
    #[Groups(['supplier'])]
    private Collection $orders;



    #[ORM\OneToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['supplier'])]
    private ?EventRecurring $recurringEvent;

    /**
     * @var Collection<int, OrderDay>
     */
    #[ORM\ManyToMany(targetEntity: OrderDay::class, inversedBy: 'suppliers', cascade: ['persist'])]
    #[Groups(['supplier'])]
    private Collection $orderDays;

    /**
     * @var Collection<int, DeliveryDay>
     */
    #[ORM\ManyToMany(targetEntity: DeliveryDay::class, inversedBy: 'suppliers', cascade: ['persist'])]
    #[Groups(['supplier'])]
    private Collection $deliveryDays;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, mappedBy: 'Suppliers', cascade: ['persist'])]
    #[Groups(['supplier'])]
    private Collection $categories;




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



    //! --------------------------------------------------------------------------------------------

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->orderDays = new ArrayCollection();
        $this->deliveryDays = new ArrayCollection();
        $this->categories = new ArrayCollection();

    }

    //! --------------------------------------------------------------------------------------------

    public function getBusiness(): ?Business
    {
        return $this->business;
    }

    public function setBusiness(?Business $business): static
    {
        $this->business = $business;

        return $this;
    }

    public function getRecurringEvent(): ?EventRecurring
    {
        return $this->recurringEvent;
    }

    public function setRecurringEvent(?EventRecurring $recurringEvent): static
    {
        $this->recurringEvent = $recurringEvent;

        return $this;
    }

    //! --------------------------------------------------------------------------------------------


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
    public function removeAllProducts(): self
    {
        foreach ($this->products as $product) {
            $this->removeProduct($product);
        }

        return $this;
    }

    //! --------------------------------------------------------------------------------------------
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
        $this->orders->removeElement($order);
        return $this;
    }

    public function removeAllOrders(): self
    {
        foreach ($this->orders as $order) {
            $this->removeOrder($order);
        }

        return $this;
    }


    //! --------------------------------------------------------------------------------------------

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
            $orderDay->addSupplier($this);
           
        }

        return $this;
    }

    public function removeOrderDay(OrderDay $orderDay): self
    {
        if ($this->orderDays->contains($orderDay)) {
            $this->orderDays->removeElement($orderDay);
            $orderDay->removeSupplier($this);
        }

        return $this;
    }

    public function removeAllOrderDays(): self
    {
        foreach ($this->orderDays as $orderDay) {
            $this->removeOrderDay($orderDay);
        }

        return $this;
    }

    //! --------------------------------------------------------------------------------------------
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

    public function removeDeliveryDay(DeliveryDay $deliveryDay): self
    {
        if ($this->deliveryDays->contains($deliveryDay)) {
            $this->deliveryDays->removeElement($deliveryDay);
            $deliveryDay->removeSupplier($this);
        }

        return $this;
    }

    public function removeAllDeliveryDays(): self
    {
        foreach ($this->deliveryDays as $deliveryDay) {
            $this->removeDeliveryDay($deliveryDay);
        }

        return $this;
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addSupplier($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): self
    {
        if ($this->categories->contains($category)) {
            $this->categories->removeElement($category);
            $category->removeSupplier($this);
        }
        return $this;
    }

    public function removeAllCategories(): self
    {
        foreach ($this->categories as $category) {
            $this->removeCategory($category);
        }

        return $this;
    }
    //! --------------------------------------------------------------------------------------------

   
    public function removeAllRelations(): self
    {
        $this->removeAllOrderDays();
        $this->removeAllDeliveryDays();
        $this->removeAllCategories();
        $this->removeAllProducts();
        $this->removeAllOrders();

        return $this;
    }



}
