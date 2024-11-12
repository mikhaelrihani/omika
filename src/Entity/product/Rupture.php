<?php

namespace App\Entity\Product;

use App\Entity\BaseEntity;
use App\Repository\Product\RuptureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Product\Product;

/**
 * @ORM\Entity(repositoryClass=RuptureRepository::class)
 */
#[ORM\Entity(repositoryClass: RuptureRepository::class)]
class Rupture extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Info should not be blank.")]
    private ?string $info = null;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Origin should not be blank.")]
    private ?string $origin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uniqueSolution = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $solution = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Status should not be blank.")]
    private ?string $status = null;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\product\Product", inversedBy="rupture")
     * @ORM\JoinColumn(nullable=false)
     */
    #[ORM\OneToOne(targetEntity: Product::class, inversedBy: 'rupture')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInfo(): ?string
    {
        return $this->info;
    }

    public function setInfo(string $info): static
    {
        $this->info = $info;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): static
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

    public function getSolution(): ?string
    {
        return $this->solution;
    }

    public function setSolution(?string $solution): static
    {
        $this->solution = $solution;

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

    public function getProduct(): ?Product
    {
        return $this->product;
    }
    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }
    
}
