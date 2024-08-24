<?php

namespace App\Entity\carte;

use App\Entity\BaseEntity;
use App\Repository\carte\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
class Menu extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank(message: "Week should not be blank.")]
    private ?int $week = null;

    #[ORM\Column(length: 100,nullable: false)]
    #[Assert\NotBlank(message: "Author should not be blank.")]
    private ?string $author = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fishGrill = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $meatGrill = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $chefSpecial = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $special = null;

    #[ORM\Column(length: 100,nullable: false)]
    #[Assert\NotBlank]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)] // nullable true pour permettre les drafts sans PDF
    private ?string $pdfPath = null;


    /**
     * @var Collection<int, Dod>
     */
    #[ORM\OneToMany(targetEntity: Dod::class, mappedBy: 'menu')]
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

      /**
     * @Assert\Callback
     */
    public function validatePdfPath(ExecutionContextInterface $context): void
    {
        // Si le statut n'est pas "draft", le pdfPath ne doit pas être null
        if ($this->status !== 'draft' && empty($this->pdfPath)) {
            $context->buildViolation('Le champ pdfPath est requis lorsque le menu n\'est pas un draft.')
                ->atPath('pdfPath')
                ->addViolation();
        }
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
