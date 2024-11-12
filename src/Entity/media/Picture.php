<?php

namespace App\Entity\Media;

use App\Entity\BaseEntity;
use App\Repository\Media\PictureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PictureRepository::class)]
class Picture extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Mime $mime = null;

    #[ORM\Column(length: 50,nullable: false)]
    #[Assert\NotBlank(message: "Slug should not be blank.")]
    private ?string $slug = null;

    #[ORM\Column(length: 100,nullable: false)]
    #[Assert\NotBlank(message: "Name should not be blank.")]
    private ?string $name = null;

    #[ORM\Column(length: 255,nullable: false)]
    #[Assert\NotBlank(message: "Path should not be blank.")]
    private ?string $path = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMime(): ?Mime
    {
        return $this->mime;
    }

    public function setMime(?Mime $mime): static
    {
        $this->mime = $mime;

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

}
