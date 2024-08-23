<?php

namespace App\Entity\recipe;
use App\Entity\BaseEntity;
use App\Repository\recipe\RecipeStepRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeStepRepository::class)]
class RecipeStep extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 1000)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $orderStep = null;

    #[ORM\ManyToOne(inversedBy: 'recipeSteps')]
    #[ORM\JoinColumn(nullable: false)]
    private ?recipe $recipe = null;


    public function getId(): ?int
    {
        return $this->id;
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

    public function getOrderStep(): ?int
    {
        return $this->orderStep;
    }

    public function setOrderStep(int $orderStep): static
    {
        $this->orderStep = $orderStep;

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

   
}
