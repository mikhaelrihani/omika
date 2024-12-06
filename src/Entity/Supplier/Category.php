<?php

namespace App\Entity\Supplier;

use App\Entity\BaseEntity;
use App\Repository\Supplier\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['supplier'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['supplier'])]
    private ?string $name = null;

    /**
     * @var Collection<int, Supplier>
     */
    #[ORM\ManyToMany(targetEntity: Supplier::class, inversedBy: 'categories')]
    private Collection $Suppliers;

    public function __construct()
    {
        $this->Suppliers = new ArrayCollection();
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

    /**
     * @return Collection<int, Supplier>
     */
    public function getSuppliers(): Collection
    {
        return $this->Suppliers;
    }

    public function addSupplier(Supplier $supplier): static
    {
        if (!$this->Suppliers->contains($supplier)) {
            $this->Suppliers->add($supplier);
        }

        return $this;
    }

    public function removeSupplier(Supplier $supplier): static
    {
        $this->Suppliers->removeElement($supplier);

        return $this;
    }
}
