<?php

namespace App\Entity\Event;

use App\Entity\BaseEntity;
use App\Entity\User\user;
use App\Repository\Event\TagInfoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagInfoRepository::class)]
#[ORM\Index(name: "Taginfo_user_tag_idx", columns: ["user_id", "tag_id"])]

class TagInfo extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tagInfos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?tag $tag = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?user $user = null;

    #[ORM\Column(nullable: true)]
    private ?int $unreadInfoCount = null;

 
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTag(): ?tag
    {
        return $this->tag;
    }

    public function setTag(?tag $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function getUser(): ?user
    {
        return $this->user;
    }

    public function setUser(?user $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getUnreadInfoCount(): ?int
    {
        return $this->unreadInfoCount;
    }

    public function setUnreadInfoCount(?int $unreadInfoCount): static
    {
        $this->unreadInfoCount = $unreadInfoCount;

        return $this;
    }

   
}
