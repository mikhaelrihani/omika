<?php

namespace App\Entity\Media;

use App\Entity\BaseEntity;
use App\Repository\Media\TemplateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TemplateRepository::class)]
class Template extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255,nullable: false)]
    #[Assert\NotBlank(message: "Name should not be blank.")]
    private ?string $name = null;

    #[ORM\Column(length: 1000,nullable: false)]
    #[Assert\NotBlank(message: "Text should not be blank.")]
    private ?string $text = null;

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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

  
}
