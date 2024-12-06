<?php

namespace App\Entity\Supplier;

use App\Entity\BaseEntity;
use App\Repository\Supplier\OrderDayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: OrderDayRepository::class)]
class OrderDay extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['supplier'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['supplier'])]
    private ?int $day = null;

    /**
     * @var Collection<int, Supplier>
     */
    #[ORM\ManyToMany(targetEntity: Supplier::class, mappedBy: 'orderDays', cascade: ['persist'])]
    private Collection $suppliers;

    public function __construct()
    {
        $this->suppliers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDay(): ?int
    {
        return $this->day;
    }

    public function setDay(int $day): static
    {
        $this->day = $day;

        return $this;
    }
    public function removeSupplier(Supplier $supplier): self
    {
        if ($this->suppliers->contains($supplier)) {
            $this->suppliers->removeElement($supplier);
        }

        return $this;
    }

}
