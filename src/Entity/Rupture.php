<?php

namespace App\Entity;

use App\Repository\RuptureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RuptureRepository::class)]
class Rupture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $info = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uniqueSolution = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    /**
     * @var Collection<int, ProductRupture>
     */
    #[ORM\OneToMany(targetEntity: ProductRupture::class, mappedBy: 'rupture')]
    private Collection $productRuptures;

    public function __construct()
    {
        $this->productRuptures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInfo(): ?string
    {
        return $this->info;
    }

    public function setInfo(?string $info): static
    {
        $this->info = $info;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getUniqueSolution(): ?string
    {
        return $this->uniqueSolution;
    }

    public function setUniqueSolution(?string $uniqueSolution): static
    {
        $this->uniqueSolution = $uniqueSolution;

        return $this;
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
     * @return Collection<int, ProductRupture>
     */
    public function getProductRuptures(): Collection
    {
        return $this->productRuptures;
    }

    public function addProductRupture(ProductRupture $productRupture): static
    {
        if (!$this->productRuptures->contains($productRupture)) {
            $this->productRuptures->add($productRupture);
            $productRupture->setRupture($this);
        }

        return $this;
    }

    public function removeProductRupture(ProductRupture $productRupture): static
    {
        if ($this->productRuptures->removeElement($productRupture)) {
            // set the owning side to null (unless already changed)
            if ($productRupture->getRupture() === $this) {
                $productRupture->setRupture(null);
            }
        }

        return $this;
    }
}
