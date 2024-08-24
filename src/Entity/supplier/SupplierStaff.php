<?php

namespace App\Entity\supplier;

use App\Entity\BaseEntity;
use App\Entity\user\Contact;
use App\Repository\supplier\SupplierStaffRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupplierStaffRepository::class)]
class SupplierStaff extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $contact = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class, inversedBy: 'supplierStaff')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Supplier $supplier = null;

   
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(Contact $contact): static
    {
        $this->contact = $contact;

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

}
