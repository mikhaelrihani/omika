<?php

namespace App\Entity\user;

use App\Entity\BaseEntity;
use App\Repository\user\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\media\Picture;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity(fields: ['avatar'], message: 'This picture is already used as an avatar by another user.')]
// TODO : MIGRATION : $this->addSql('ALTER TABLE user ADD CONSTRAINT UNIQUE (avatar_id)');
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID, nullable: false)]
    #[Assert\NotBlank]
    private ?string $uuid = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "First name should not be blank.")]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: "Surname should not be blank.")]
    private ?string $surname = null;

    #[ORM\Column(length: 20, nullable: false)]
    #[Assert\NotBlank(message: "Phone number should not be blank.")]
    #[Assert\Length(
        min: 10,
        max: 20,
        minMessage: "Phone number must be at least {{ limit }} characters long.",
        maxMessage: "Phone number cannot be longer than {{ limit }} characters."
    )]
    #[Assert\Regex(
        pattern: "/^\+?[0-9\s]*$/",
        message: "Phone number should contain only digits, spaces, and an optional leading '+' sign."
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(
        min: 10,
        max: 20,
        minMessage: "WhatsApp number must be at least {{ limit }} characters long.",
        maxMessage: "WhatsApp number cannot be longer than {{ limit }} characters."
    )]
    #[Assert\Regex(
        pattern: "/^\+?[0-9\s]*$/",
        message: "WhatsApp number should contain only digits, spaces, and an optional leading '+' sign."
    )]
    private ?string $whatsapp = null;


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $job = null;




    //! differents properties than contact entity

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?userLogin $userLogin = null;

    #[ORM\OneToOne(cascade: ['persist'])]
    private ?picture $avatar = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\NotNull(message: "Late count should not be null.")]
    private int $lateCount = 0;

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\NotNull(message: "Absent count should not be null.")]
    private int $absentCount = 0;

    #[ORM\Column(length: 50, nullable: false)]
    #[Assert\NotBlank(message: "Pseudo should not be blank.")]
    private ?string $pseudo = null;




    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): static
    {
        $this->surname = $surname;

        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getWhatsapp(): ?string
    {
        return $this->whatsapp;
    }

    public function setWhatsapp(?string $whatsapp): static
    {
        $this->whatsapp = $whatsapp;

        return $this;
    }

    public function getUserLogin(): ?userLogin
    {
        return $this->userLogin;
    }

    public function setUserLogin(userLogin $userLogin): static
    {
        $this->userLogin = $userLogin;

        return $this;
    }



    public function getAvatar(): ?picture
    {
        return $this->avatar;
    }

    public function setAvatar(?picture $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

   

    public function getLateCount(): ?int
    {
        return $this->lateCount;
    }

    public function setLateCount(int $lateCount): static
    {
        $this->lateCount = $lateCount;

        return $this;
    }

    public function getAbsentCount(): ?int
    {
        return $this->absentCount;
    }

    public function setAbsentCount(int $absentCount): static
    {
        $this->absentCount = $absentCount;

        return $this;
    }

    public function getJob(): ?string
    {
        return $this->job;
    }

    public function setJob(?string $job): static
    {
        $this->job = $job;

        return $this;
    }


}
