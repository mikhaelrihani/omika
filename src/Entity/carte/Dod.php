<?php

namespace App\Entity\carte;

use App\Entity\BaseEntity;
use App\Repository\carte\DodRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DodRepository::class)]
class Dod extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Dod Name should not be blank.")]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Dod Description should not be blank.")]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $infos = null;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'dods')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull(message: "A Dod must be associated with a Menu.")]
    private ?Menu $menu = null;

    #[ORM\Column]
    private ?int $orderDay = null;
    

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getInfos(): ?string
    {
        return $this->infos;
    }

    public function setInfos(?string $infos): static
    {
        $this->infos = $infos;

        return $this;
    }

    public function getMenu(): ?menu
    {
        return $this->menu;
    }

    public function setMenu(?menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getOrderDay(): ?int
    {
        return $this->orderDay;
    }

    public function setOrderDay(int $orderDay): static
    {
        $this->orderDay = $orderDay;

        return $this;
    }

}
