<?php

namespace App\Entity\recipe;

use App\Entity\BaseEntity;
use App\Repository\recipe\RecipeAdviseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipeAdviseRepository::class)]
class RecipeAdvise extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable:false)]
    #[Assert\NotBlank(message: "Order Advise should not be blank.")]
    private ?int $orderAdvise = null;

    #[ORM\Column(length: 1000, nullable: false)]
    #[Assert\NotBlank(message: "Description should not be blank.")]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity:Recipe::class,inversedBy: 'recipeAdvises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recipe $recipe = null;

   
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

   
}
