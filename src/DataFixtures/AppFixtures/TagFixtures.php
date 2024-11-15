<?php

namespace App\DataFixtures\AppFixtures;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
use App\Entity\Event\TagInfo;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TagFixtures extends BaseFixtures implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->createTags();
        $manager->flush();
    }


    private function createTags(): void
    {
        // un tag peut être créer pour chaque jour et pour chaque section de chaque side.
        // un event n'est pas lié à un tag , mais la création d'un event incremente le count du tag existant correspondant(section, sidee, duedate)
        // ou crée un nouveau tag et l'incremente de 1.

        // on recupere tous les events
        $events = $this->retrieveEntities("event", $this);

        foreach ($events as $event) {
            // le seul event qui n'interagit pas avec un tag est un event info qui a été lu par tous les users. 
            if (!$event->getInfo() || !$event->getInfo()->isFullyRead()) {

                $day = $event->getDueDate();
                $side = $event->getSide();
                $section = $event->getSection()->getName();
                $createdAt = $updatedAt = $event->getCreatedAt();

                // we check if the tag already exists 
                $tag = $this->em->getRepository(Tag::class)->findOneByDaySideSection($day, $side, $section);
                if (!$tag) {
                    $tag = new Tag();
                    $tag
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt($updatedAt)
                        ->setSection($section)
                        ->setDay($day)
                        ->setDateStatus($event->getDateStatus())
                        ->setActiveDay($event->getActiveDay())
                        ->setSide($side);

                    $this->em->persist($tag);
                }
                $this->updateTagCount($tag, $event);
            }
        }
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

        // je récupère les users avec lesquels l'info a été partagée et pour chacun on vérifie si l'info est non lue, dans ce cas on imcrémente de 1 le tag associé.
        $sharedWith = $event->getInfo()->getSharedWith();
        $users = [];
        foreach ( $sharedWith as $userInfo) {

            if (!$userInfo->isRead()) {
                $users[] = $userInfo->getUser();
            }
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



    public function getDependencies(): array
    {
        return [
            EventFixtures::class,
        ];
    }

}
