<?php

namespace App\Entity\carte;

use App\Repository\carte\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $week = null;

    #[ORM\Column(length: 100)]
    private ?string $author = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fishGrill = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $meatGrill = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $chefSpecial = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $special = null;

    #[ORM\Column(length: 100)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $pdfPath = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    /**
     * @var Collection<int, Dod>
     */
    #[ORM\OneToMany(targetEntity: Dod::class, mappedBy: 'menu', orphanRemoval: true)]
    private Collection $dods;

    public function __construct()
    {
        $this->dods = new ArrayCollection();
    }

  

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeek(): ?int
    {
        return $this->week;
    }

    public function setWeek(int $week): static
    {
        $this->week = $week;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getFishGrill(): ?string
    {
        return $this->fishGrill;
    }

    public function setFishGrill(?string $fishGrill): static
    {
        $this->fishGrill = $fishGrill;

        return $this;
    }

    public function getMeatGrill(): ?string
    {
        return $this->meatGrill;
    }

    public function setMeatGrill(?string $meatGrill): static
    {
        $this->meatGrill = $meatGrill;

        return $this;
    }

    public function getChefSpecial(): ?string
    {
        return $this->chefSpecial;
    }

    public function setChefSpecial(?string $chefSpecial): static
    {
        $this->chefSpecial = $chefSpecial;

        return $this;
    }

    public function getSpecial(): ?string
    {
        return $this->special;
    }

    public function setSpecial(?string $special): static
    {
        $this->special = $special;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;

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
     * @return Collection<int, Dod>
     */
    public function getDods(): Collection
    {
        return $this->dods;
    }

    public function addDod(Dod $dod): static
    {
        if (!$this->dods->contains($dod)) {
            $this->dods->add($dod);
            $dod->setMenu($this);
        }

        return $this;
    }

    public function removeDod(Dod $dod): static
    {
        if ($this->dods->removeElement($dod)) {
            // set the owning side to null (unless already changed)
            if ($dod->getMenu() === $this) {
                $dod->setMenu(null);
            }
        }

        return $this;
    }

}
