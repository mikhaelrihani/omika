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
        $manager->flush();
        $this->createTags();
    }

    private function createTags(): void
    {
        $randomNumber = $this->faker->numberBetween(1, 5);
        for ($i = 0; $i < $randomNumber; $i++) {
            $this->createOneTag();
        }
        ;
    }

    private function createOneTag(): void
    {
        // un tag peut être créer pour chaque jour et pour chaque section de chaque side.
        // un tag correspond uniquement a un jour et a une section.
        // le seul event qui n a pas de tag est un event info qui a été lu par tous les users. 

        // on recupere tous les events
        $events = $this->retrieveEntities("event", $this);
       
        foreach ($events as $event) {
            if ($event->getInfo() ? !$event->getInfo()->isFullyRead() : true) {

                $day = $event->getDueDate();
                $side = $event->getSide();
                $section = $event->getSection()->getName();
                $createdAt = $updatedAt = $event->getCreatedAt();

                // we check if the tag already exists 
                $tag = $this->tagRepository->findOneByDaySideSection($day, $side, $section);
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

                    $this->updateTagCount($tag, $event);
                }
            } else {
                continue;
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
        if ($tag->getId() === null) {
            $tag
                ->setUpdatedAt($event->getUpdatedAt())
                ->setTaskCount(null);
            $this->em->persist($tag);
            $this->em->flush(); // Flush pour générer un ID pour le tag en base pour löa methode "findOneByUserAndTag"
        }

        // je récupère les users qui n'ont pas lu l'info pour ensuite ajouter un count au tag
        $eventsSharedInfos = $event->getInfo()->getEventSharedInfo();
        $users = [];
        foreach ($eventsSharedInfos as $eventSharedInfo) {

            if (!$eventSharedInfo->isRead()) {
                $users[] = $eventSharedInfo->getUser();
            }
        }

        // pour chaque user je cree un tag info en vérifiant que ce tag info n'existe pas déjà.
        foreach ($users as $user) {

            $tagInfo = $this->tagInfoRepository->findOneByUserAndTag($user, $tag);
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
            }
            //!penser a remove le tag pour les users qui ont  lu l info 
            $tag->addTagInfo($tagInfo);
            $this->em->flush();

        }

    }
    private function setTaskTagCount(Tag $tag, Event $event): void
    {
        // Récupérer le compteur actuel, ou initialiser à zéro si nul
        $count = $tag->getTaskCount() ?? 0;

        // Vérifier le statut de la tâche de l'événement
        $statut = $event->getTask()->getTaskStatus();

        // Mettre à jour le compteur en fonction du statut
        if ($statut === "unrealised") {
            $count = max(0, $count - 1); // Empêcher le compteur de descendre en dessous de zéro
        } else {
            $count++;
        }

        $tag->setTaskCount($count);
        $tag->setUpdatedAt($event->getUpdatedAt());
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
