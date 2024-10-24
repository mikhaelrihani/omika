<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Repository\Event\TagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $section; // Section de l'événement

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeImmutable $day; // Jour concerné

    #[ORM\Column(length: 255)]
    private string $date_status; // Statut de la date (ex. "past", "active_day_range", "future")

    #[ORM\Column(type: Types::INTEGER)]
    private int $task_count = 0; // Compte des tâches actives

    #[ORM\Column(type: Types::JSON)]
    private ?array $info_count = null; // JSON des comptes d'infos non lues pour chaque utilisateur

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $allUserRead = false;

    #[ORM\Column(nullable: true)]
    private ?array $active_day_range = null; // Indique si tous les utilisateurs ont lu les informations

 
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSection(): string
    {
        return $this->section;
    }

    public function setSection(string $section): static
    {
        $this->section = $section;
        return $this;
    }

    public function getDay(): \DateTimeInterface
    {
        return $this->day;
    }

    public function setDay(\DateTimeInterface $day): static
    {
        $this->day = $day;
        return $this;
    }

    public function getDateStatus(): string
    {
        return $this->date_status;
    }

    public function setDateStatus(string $date_status): static
    {
        $this->date_status = $date_status;
        return $this;
    }

    public function getTaskCount(): int
    {
        return $this->task_count;
    }

    public function setTaskCount(int $task_count): static
    {
        $this->task_count = $task_count;
        return $this;
    }

    public function getInfoCount(): array
    {
        return $this->info_count;
    }

    public function setInfoCount(array $info_count): static
    {
        $this->info_count = $info_count;
        return $this;
    }

    public function isAllUserRead(): bool
    {
        return $this->allUserRead;
    }

    public function setAllUserRead(bool $allUserRead): static
    {
        $this->allUserRead = $allUserRead;
        return $this;
    }

    public function getActiveDayRange(): ?array
    {
        return $this->active_day_range;
    }

    public function setActiveDayRange(?array $active_day_range): static
    {
        $this->active_day_range = $active_day_range;

        return $this;
    }

   
}
