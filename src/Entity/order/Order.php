<?php

namespace App\Entity\order;

use App\Repository\order\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\user\User; 
use App\Entity\supplier\Supplier;
use Symfony\Component\Validator\Constraints as Assert; 

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $deliveryDate = null;

    #[ORM\Column(length: 255)]
    private ?string $author = null;

    #[ORM\Column(length: 255)]
    private ?string $sendingMethod = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $note = null;

    #[ORM\Column(length: 255)]
    private ?string $pdfPath = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?supplier $supplier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?user $recipient = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

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

    public function getSupplier(): ?supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getRecipient(): ?user
    {
        return $this->recipient;
    }

    public function setRecipient(?user $recipient): static
    {
        $this->recipient = $recipient;

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
}
