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
    {#$this->createTags();
        $manager->flush();
        
    }



    private function createTags(): void
    {
        // un tag peut être créer pour chaque jour et pour chaque section de chaque side.

        // on recupere tous les events
        $events = $this->retrieveEntities("event", $this);

        foreach ($events as $event) {
            // le seul event qui n a pas de tag est un event info qui a été lu par tous les users. 
            if (!$event->getInfo() || !$event->getInfo()->isFullyRead()) {

                $day = $event->getDueDate();
                if ($day instanceof \DateTimeImmutable) {
                    echo "Event ID " . $event->getId() . " has a due date of type DateTimeImmutable.\n";
                } else {
                    echo "Event ID " . $event->getId() . " does not have a due date of type DateTimeImmutable. Converting...\n";
                }
                if ($day instanceof \DateTime) {
                    $day = \DateTimeImmutable::createFromMutable($day);
                    $event->setDueDate($day);
                    echo "Converted to DateTimeImmutable.\n";
                }

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
        if ($tag->getId() === null) {
            $tag
                ->setUpdatedAt($event->getUpdatedAt())
                ->setTaskCount(null);
            $this->em->persist($tag);
            $this->em->flush(); // Flush pour générer un ID pour le tag en base pour la methode "findOneByUserAndTag"
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
                $tag->addTagInfo($tagInfo);
            }
            //!penser a remove le tag pour les users qui ont  lu l info 

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

        $count++; // Si la tâche n'est pas définie, on peut choisir d'incrémenter par défaut


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
