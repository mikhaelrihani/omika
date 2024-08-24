<?php

namespace App\Entity\carte;

use App\Entity\BaseEntity;
use App\Repository\carte\DishRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\media\Picture; 
use App\Entity\recipe\Recipe; 
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: DishRepository::class)]
class Dish extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100,nullable: false)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 5,nullable: false)]
    #[Assert\NotBlank]
    private ?string $nameGender = null;

    #[ORM\Column(length: 50,nullable: false)]
    #[Assert\NotBlank]
    private ?string $slug = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2,nullable: false)]
    #[Assert\NotBlank]
    private ?string $price = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Picture $picture = null;

    #[ORM\ManyToOne(inversedBy: 'dishes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?dishCategory $dishCategory = null;

    #[ORM\OneToOne(targetEntity: Recipe::class, inversedBy: 'dish', cascade: ['remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Recipe $recipe = null;
    

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

    public function getNameGender(): ?string
    {
        return $this->nameGender;
    }

    public function setNameGender(string $nameGender): static
    {
        $this->nameGender = $nameGender;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPicture(): ?picture
    {
        return $this->picture;
    }

    public function setPicture(picture $picture): static
    {
        $this->picture = $picture;

        return $this;
    }

    public function getDishCategory(): ?dishCategory
    {
        return $this->dishCategory;
    }

    public function setDishCategory(?dishCategory $dishCategory): static
    {
        $this->dishCategory = $dishCategory;

        return $this;
    }

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }
   
}
