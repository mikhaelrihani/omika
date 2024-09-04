<?php

namespace App\Entity\event;

use App\Entity\BaseEntity;
use App\Repository\event\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable:false)]
    #[Assert\NotBlank(message: "Side should not be blank.")]
    private ?string $side = null;

    #[ORM\Column(nullable:false)]
    #[Assert\NotBlank(message: "Visible should not be blank.")]
    private ?bool $visible = null;

    #[ORM\Column(length: 255, nullable:false)]
    #[Assert\NotBlank(message: "Status should not be blank.")]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable:false)]
    #[Assert\NotBlank(message: "Text should not be blank.")]
    private ?string $text = null;

    #[ORM\Column(length: 255, nullable:false)]
    #[Assert\NotBlank(message: "Author should not be blank.")]
    private ?string $author = null;

    #[ORM\Column(length: 255, nullable:false)]
    #[Assert\NotBlank]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable:false)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $periodeStart = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable:true)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $periodeEnd = null;

    #[ORM\Column(nullable:true) ]
    private ?bool $periodeUnlimited = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?EventSection $eventSection = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?EventFrequence $eventFrequence = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSide(): ?string
    {
        return $this->side;
    }

    public function setSide(string $side): static
    {
        $this->side = $side;

        return $this;
    }

    public function isVisible(): ?bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPeriodeStart(): ?\DateTimeInterface
    {
        return $this->periodeStart;
    }

    public function setPeriodeStart(\DateTimeInterface $periodeStart): static
    {
        $this->periodeStart = $periodeStart;

        return $this;
    }

    public function getPeriodeEnd(): ?\DateTimeInterface
    {
        return $this->periodeEnd;
    }

    public function setPeriodeEnd(?\DateTimeInterface $periodeEnd): static
    {
        $this->periodeEnd = $periodeEnd;

        return $this;
    }

    public function isPeriodeUnlimited(): ?bool
    {
        return $this->periodeUnlimited;
    }

    public function setPeriodeUnlimited(?bool $periodeUnlimited): static
    {
        $this->periodeUnlimited = $periodeUnlimited;

        return $this;
    }

    public function getEventSection(): ?EventSection
    {
        return $this->eventSection;
    }

    public function setEventSection(?EventSection $eventSection): static
    {
        $this->eventSection = $eventSection;

        return $this;
    }

    public function getEventFrequence(): ?EventFrequence
    {
        return $this->eventFrequence;
    }

    public function setEventFrequence(?EventFrequence $eventFrequence): static
    {
        $this->eventFrequence = $eventFrequence;

        return $this;
    }

    
}
