<?php

namespace App\Entity\recipe;

use App\Repository\recipe\RecipeAdviseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeAdviseRepository::class)]
class RecipeAdvise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $orderAdvise = null;

    #[ORM\Column(length: 1000)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'recipeAdvises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?recipe $recipe = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderAdvise(): ?int
    {
        return $this->orderAdvise;
    }

    public function setOrderAdvise(int $orderAdvise): static
    {
        $this->orderAdvise = $orderAdvise;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getRecipe(): ?recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?recipe $recipe): static
    {
        $this->recipe = $recipe;

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
}
