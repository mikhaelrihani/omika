<?php

namespace App\Entity\event;

use App\Entity\BaseEntity;
use App\Repository\event\EventSectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

//! event section existe pour centraliser le nom des sections , donc il faudrait repenser cette entite a section et non event section 
#[ORM\Entity(repositoryClass: EventSectionRepository::class)]
class EventSection extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25, nullable:false)]
    #[Assert\NotBlank(message: "Event Section Name should not be blank.")]
    private ?string $name = null;
    
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

}
