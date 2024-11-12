<?php

namespace App\Entity\Recipe;
use App\Entity\BaseEntity;
use App\Repository\Recipe\RecipeStepRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeStepRepository::class)]
class RecipeStep extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Description should not be blank.")]
    private ?string $description = null;

    #[ORM\Column( nullable: false)]
    #[Assert\NotBlank(message: "Order step should not be blank.")]
    private ?int $orderStep = null;

    #[ORM\ManyToOne(targetEntity:Recipe::class,inversedBy: 'recipeSteps')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recipe $recipe = null;


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

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?recipe $recipe): static
    {
        $this->recipe = $recipe;

        return $this;
    }

   
}
