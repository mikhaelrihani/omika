<?php

namespace App\Entity\Recipe;

use App\Entity\BaseEntity;
use App\Repository\Recipe\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: RecipeRepository::class)]
class Recipe extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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


    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    /**
     * @var Collection<int, Ingredient>
     */
    #[ORM\ManyToMany(targetEntity: Ingredient::class, mappedBy: 'Recipes')]
    private Collection $ingredients;

    public function __construct()
    {
        $this->recipeSteps = new ArrayCollection();
        $this->recipeAdvises = new ArrayCollection();
        $this->ingredients = new ArrayCollection();
    
    }

    public function getId(): ?int
    {
        return $this->id;
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

  
 
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @return Collection<int, Ingredient>
     */
    public function getIngredients(): Collection
    {
        return $this->ingredients;
    }

    public function addIngredient(Ingredient $ingredient): static
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients->add($ingredient);
            $ingredient->addRecipe($this);
        }

        return $this;
    }

    public function removeIngredient(Ingredient $ingredient): static
    {
        if ($this->ingredients->removeElement($ingredient)) {
            $ingredient->removeRecipe($this);
        }

        return $this;
    }
}
