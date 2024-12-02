<?php

namespace App\Entity\User;

use App\Entity\BaseEntity;
use App\Repository\User\BusinessRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BusinessRepository::class)]
class Business extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user', 'contact', 'business'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: false)]
    #[Assert\NotBlank(message: "The business name should not be blank.")]
    #[Groups(['user', 'contact', 'business'])]
    private ?string $name = null;

    /**
     * @var Collection<int, Contact>
     */
    #[ORM\OneToMany(mappedBy: 'business', targetEntity: Contact::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $contacts;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'business', cascade: ['remove'], orphanRemoval: true)]
    private Collection $users;

    public function __construct()
    {
        $this->contacts = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setBusiness($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getBusiness() === $this) {
                $user->setBusiness(null);
            }
        }

        return $this;
    }


    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setBusiness($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            // set the owning side to null (unless already changed)
            if ($contact->getBusiness() === $this) {
                $contact->setBusiness(null);
            }
        }

        return $this;
    }
}
