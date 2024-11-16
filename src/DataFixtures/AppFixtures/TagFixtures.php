<?php

namespace App\DataFixtures\AppFixtures;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
use App\Entity\Event\TagInfo;
use App\Entity\Event\TagTask;
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

            if ($event->getInfo() && $event->getInfo()->isFullyRead()) {
                continue;
            } else {

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
                $this->em->flush();
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
            ->setUpdatedAt($event->getUpdatedAt());
        // un tag est unique a sa section/day/side et est partage entre differents user
        // je récupère les users avec lesquels l'info a été partagée et pour chacun on vérifie si l'info est non lue, dans ce cas on imcrémente de 1 le tag associé.
        $sharedWith = $event->getInfo()->getSharedWith();
        $users = [];
        foreach ($sharedWith as $userInfo) {

            if (!$userInfo->isRead()) {
                $users[] = $userInfo->getUser();
            }
        }

        // pour chaque user j associe le tag partagé avec un tag info en vérifiant que le tag info n'existe pas déjà, dans ce ca son cree la relation taginfo
        foreach ($users as $user) {
            $tagInfo = $this->em->getRepository(TagInfo::class)->findOneByUserAndTag_info($user, $tag);
            if ($tagInfo) {
                $count = $tagInfo->getUnreadInfoCount();
                $count++;
                $tagInfo->setUnreadInfoCount($count);
                $tagInfo->setUpdatedAt($event->getCreatedAt());
            } else {
                $tagInfo = (new TagInfo())
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

    /**
     * Met à jour le compteur des tâches associées à un tag pour chaque utilisateur.
     *
     * Cette méthode gère l'association entre une tâche d'événement et un tag. 
     * Pour chaque utilisateur avec lequel la tâche est partagée, elle met à jour 
     * ou crée un enregistrement `TagTask` :
     * - Si une entité `TagTask` existe déjà pour l'utilisateur et le tag, 
     *   son compteur est incrémenté ou décrémenté selon le statut de la tâche.
     * - Si aucune entité `TagTask` n'existe, une nouvelle est créée avec un compteur initialisé à 1.
     *
     * @param Tag $tag L'entité Tag à associer ou mettre à jour.
     * @param Event $event L'entité Event contenant les détails de la tâche.
     * 
     *
     * @return void
     */
    private function setTaskTagCount(Tag $tag, Event $event): void
    {
        $tag->setUpdatedAt($event->getUpdatedAt());

        // Récupère les utilisateurs associés à la tâche.
        $users = $event->getTask()->getSharedWith();

        // Met à jour ou crée un TagTask pour chaque utilisateur.
        foreach ($users as $user) {
            // Recherche une association existante entre le tag et l'utilisateur.

            $tagTask = $this->em->getRepository(TagTask::class)->findOneByUserAndTag_task($user, $tag);

            if ($tagTask) {
                // Mise à jour du compteur si l'entité existe.
                $count = $tagTask->getTagCount();
                $status = $event->getTask()->getTaskStatus();

                // Incrémente ou décrémente selon le statut.
                if ($status !== "unrealised") {
                    $count++;
                } else {
                    $count = max(0, $count - 1); // Assure que le compteur ne passe pas en dessous de 0.
                }
                $tagTask->setTagCount($count);
                $tagTask->setUpdatedAt($event->getCreatedAt());
            } else {
                // Création d'une nouvelle entité si aucune association n'existe.
                $tagTask = (new TagTask())
                    ->setUser($user)
                    ->setTag($tag)
                    ->setTagCount(1)
                    ->setCreatedAt($event->getCreatedAt())
                    ->setUpdatedAt($event->getCreatedAt());
                $this->em->persist($tagTask);
                $tag->addTagTask($tagTask);
            }
        }

        // Sauvegarde toutes les modifications en base.
        $this->em->flush();
    }


    public function getDependencies(): array
    {
        return [
            EventFixtures::class,
        ];
    }

}
