<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
use App\Entity\Event\TagInfo;
use Doctrine\ORM\EntityManagerInterface;


class TagService
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected EventService $eventService
    ) {
    }


    public function setTag($event): void
    {
        $day = $event->getDueDate();
        $side = $event->getSide();
        $section = $event->getSection()->getName();

        // we check if the tag already exists 
        $tag = $this->em->getRepository(Tag::class)->findOneByDaySideSection($day, $side, $section);
        if (!$tag) {
            $tag = new Tag();
            $tag
                ->setSection($section)
                ->setDay($day)
                ->setDateStatus($event->getDateStatus())
                ->setActiveDay($event->getActiveDay())
                ->setSide($side);

            $this->em->persist($tag);
        }
        $this->updateTagCount($tag, $event);
    }


    private function updateTagCount(Tag $tag, Event $event): void
    {
        $type = $event->getType();
        ($type === "task") ?
            $this->setTaskTagCount($tag, $event) :
            $this->setInfoTagCount($tag, $event);

    }
    private function setInfoTagCount(Tag $tag, Event $event): void
    {
        $tag
            ->setUpdatedAt($event->getUpdatedAt())
            ->setTaskCount(null);

        if ($tag->getId() === null) {
            $this->em->persist($tag);
            $this->em->flush(); // Flush pour générer un ID pour le tag en base pour la methode "findOneByUserAndTag"
        }

        // je récupère les users avec lesquels l'info a été partagée et pour chacun on imcrémente de 1 le tag associé.
        $eventsSharedInfos = $event->getInfo()->getEventSharedInfo();
        $users = [];
        foreach ($eventsSharedInfos as $eventSharedInfo) {
            $users[] = $eventSharedInfo->getUser();
        }

        // pour chaque user je cree un tag info en vérifiant que ce tag info n'existe pas déjà.
        foreach ($users as $user) {
            $tagInfo = $this->em->getRepository(TagInfo::class)->findOneByUserAndTag($user, $tag);
            if ($tagInfo) {
                $count = $tagInfo->getUnreadInfoCount();
                $count++;
                $tagInfo->setUnreadInfoCount($count);
                $tagInfo->setUpdatedAt($event->getCreatedAt());
            } else {
                $tagInfo = new TagInfo();
                $tagInfo
                    ->setUser($user)
                    ->setTag($tag)
                    ->setUnreadInfoCount(1)
                    ->setCreatedAt($event->getCreatedAt())
                    ->setUpdatedAt($event->getCreatedAt());
                $this->em->persist($tagInfo);
                $tag->addTagInfo($tagInfo);
            }
        }
        $this->em->flush();
    }

    private function setTaskTagCount(Tag $tag, Event $event): void
    {
        // Récupérer le compteur actuel, ou initialiser à zéro si nul
        $count = $tag->getTaskCount() ?? 0;

        // Vérifier le statut de la tâche de l'événement
        $statut = $event->getTask()->getTaskStatus();
        // Choisir si on décrémente pour unrealised ou on incrémente pour tout
        if ($statut !== "unrealised") {
            $count++;
        } else {
            $count = max(0, $count - 1); // Décrémenter uniquement si nécessaire
        }

        $tag->setTaskCount($count)
            ->setUpdatedAt($event->getUpdatedAt());
        $this->em->persist($tag);
        $this->em->flush();
    }

}