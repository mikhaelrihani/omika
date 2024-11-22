<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\SectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;


#[ORM\Entity(repositoryClass: SectionRepository::class)]
class Section extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['event', 'eventRecurring'])]
    private ?int $id = null;

    #[ORM\Column(length: 25, nullable:false)]
    #[Assert\NotBlank(message: "Event Section Name should not be blank.")]
    #[Groups(['event', 'eventRecurring'])]
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
