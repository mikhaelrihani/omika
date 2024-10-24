<?php

namespace App\Entity\order;

use App\Entity\BaseEntity;
use App\Repository\order\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Product\Supplier;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface; 

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE,nullable: false)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $deliveryDate = null;

    #[ORM\Column(length: 255,nullable: false)]
    #[Assert\NotBlank(message: "Author should not be blank.")]
    private ?string $author = null;

    #[ORM\Column(length: 255,nullable: false)]
    #[Assert\NotBlank(message: "Sending method should not be blank.")]
    private ?string $sendingMethod = null;

    #[ORM\Column(length: 255,nullable: false)]
    #[Assert\NotBlank(message: "Status should not be blank.")]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $note = null;

    #[ORM\Column(length: 255)]
    private ?string $pdfPath = null;

    #[ORM\ManyToOne(targetEntity:Supplier::class,inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Supplier $supplier = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeliveryDate(): ?\DateTimeInterface
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(\DateTimeInterface $deliveryDate): static
    {
        $this->deliveryDate = $deliveryDate;

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

    public function getSendingMethod(): ?string
    {
        return $this->sendingMethod;
    }

    public function setSendingMethod(string $sendingMethod): static
    {
        $this->sendingMethod = $sendingMethod;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(string $note): static
    {
        $this->note = $note;

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

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

      /**
     * @Assert\Callback
     */
    public function validatePdfPath(ExecutionContextInterface $context): void
    {
        // Si le statut n'est pas "draft", le pdfPath ne doit pas être null
        if ($this->status !== 'draft' && empty($this->pdfPath)) {
            $context->buildViolation('Le champ pdfPath est requis lorsque la commande n\'est pas un draft.')
                ->atPath('pdfPath')
                ->addViolation();
        }
    }

}
