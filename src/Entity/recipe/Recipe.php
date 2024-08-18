<?php

namespace App\Entity\recipe;

use App\Repository\recipe\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\carte\dish; 
use App\Entity\product\Product; 

#[ORM\Entity(repositoryClass: RecipeRepository::class)]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'recipe', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?dish $dish = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    /**
     * @var Collection<int, RecipeStep>
     */
    #[ORM\OneToMany(targetEntity: RecipeStep::class, mappedBy: 'recipe', orphanRemoval: true)]
    private Collection $recipeSteps;

    /**
     * @var Collection<int, RecipeAdvise>
     */
    #[ORM\OneToMany(targetEntity: RecipeAdvise::class, mappedBy: 'recipe', orphanRemoval: true)]
    private Collection $recipeAdvises;

    /**
     * @var Collection<int, ingredient>
     */
    #[ORM\ManyToMany(targetEntity: ingredient::class)]
    private Collection $ingredients;

    /**
     * @var Collection<int, product>
     */
    #[ORM\ManyToMany(targetEntity: product::class, inversedBy: 'recipes')]
    private Collection $products;

    public function __construct()
    {
        $this->recipeSteps = new ArrayCollection();
        $this->recipeAdvises = new ArrayCollection();
        $this->ingredients = new ArrayCollection();
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDish(): ?dish
    {
        return $this->dish;
    }

    public function setDish(dish $dish): static
    {
        $this->dish = $dish;

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
     * @return Collection<int, RecipeStep>
     */
    public function getRecipeSteps(): Collection
    {
        return $this->recipeSteps;
    }

    public function addRecipeStep(RecipeStep $recipeStep): static
    {
        if (!$this->recipeSteps->contains($recipeStep)) {
            $this->recipeSteps->add($recipeStep);
            $recipeStep->setRecipe($this);
        }

        return $this;
    }

    public function removeRecipeStep(RecipeStep $recipeStep): static
    {
        if ($this->recipeSteps->removeElement($recipeStep)) {
            // set the owning side to null (unless already changed)
            if ($recipeStep->getRecipe() === $this) {
                $recipeStep->setRecipe(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RecipeAdvise>
     */
    public function getRecipeAdvises(): Collection
    {
        return $this->recipeAdvises;
    }

    public function addRecipeAdvise(RecipeAdvise $recipeAdvise): static
    {
        if (!$this->recipeAdvises->contains($recipeAdvise)) {
            $this->recipeAdvises->add($recipeAdvise);
            $recipeAdvise->setRecipe($this);
        }

        return $this;
    }

    public function removeRecipeAdvise(RecipeAdvise $recipeAdvise): static
    {
        if ($this->recipeAdvises->removeElement($recipeAdvise)) {
            // set the owning side to null (unless already changed)
            if ($recipeAdvise->getRecipe() === $this) {
                $recipeAdvise->setRecipe(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ingredient>
     */
    public function getIngredient(): Collection
    {
        return $this->ingredients;
    }

    public function addIngredient(ingredient $ingredient): static
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients->add($ingredient);
        }

        return $this;
    }

    public function removeIngredient(ingredient $ingredient): static
    {
        $this->ingredients->removeElement($ingredient);

        return $this;
    }

    /**
     * @return Collection<int, product>
     */
    public function getProduct(): Collection
    {
        return $this->products;
    }

    public function addProduct(product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    public function removeProduct(product $product): static
    {
        $this->products->removeElement($product);

        return $this;
    }
}
