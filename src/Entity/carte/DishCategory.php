<?php

namespace App\Entity\carte;

use App\Entity\BaseEntity;
use App\Repository\carte\DishCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\media\Picture;
use Symfony\Component\Validator\Constraints as Assert; 

#[ORM\Entity(repositoryClass: DishCategoryRepository::class)]
class DishCategory extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255,nullable: false)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?picture $picture = null;

    /**
     * @var Collection<int, Dish>
     */
    #[ORM\OneToMany(targetEntity: Dish::class, mappedBy: 'dishCategory')]
    private Collection $dishes;

    public function __construct()
    {
        $this->dishes = new ArrayCollection();
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

    public function getPicture(): ?picture
    {
        return $this->picture;
    }

    public function setPicture(picture $picture): static
    {
        $this->picture = $picture;

        return $this;
    }


    /**
     * @return Collection<int, Dish>
     */
    public function getDishes(): Collection
    {
        return $this->dishes;
    }

   
}
