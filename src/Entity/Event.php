<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $side = null;

    #[ORM\Column]
    private ?bool $visible = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $text = null;

    #[ORM\Column(length: 255)]
    private ?string $author = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $periodeStart = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $periodeEnd = null;

    #[ORM\Column]
    private ?bool $periodeUnlimited = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?eventSection $eventSection = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?eventFrequence $eventFrequence = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

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

    public function setPeriodeEnd(\DateTimeInterface $periodeEnd): static
    {
        $this->periodeEnd = $periodeEnd;

        return $this;
    }

    public function isPeriodeUnlimited(): ?bool
    {
        return $this->periodeUnlimited;
    }

    public function setPeriodeUnlimited(bool $periodeUnlimited): static
    {
        $this->periodeUnlimited = $periodeUnlimited;

        return $this;
    }

    public function getEventSection(): ?eventSection
    {
        return $this->eventSection;
    }

    public function setEventSection(?eventSection $eventSection): static
    {
        $this->eventSection = $eventSection;

        return $this;
    }

    public function getEventFrequence(): ?eventFrequence
    {
        return $this->eventFrequence;
    }

    public function setEventFrequence(?eventFrequence $eventFrequence): static
    {
        $this->eventFrequence = $eventFrequence;

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
