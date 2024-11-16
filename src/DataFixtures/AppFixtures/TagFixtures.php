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
    /**
     * Chargement des fixtures dans la base de données.
     * Cette méthode appelle la création des tags pour les événements existants.
     * 
     * @param ObjectManager $manager Le gestionnaire d'entités Doctrine.
     */
    public function load(ObjectManager $manager): void
    {
        $this->createTags();
        $manager->flush();
    }

    /**
     * Crée les tags pour les événements récupérés.
     * Un tag est créé pour chaque jour, section, et côté d'un événement.
     * Si un tag correspondant existe déjà, son compteur est mis à jour.
     */
    private function createTags(): void
    {
        // On récupère tous les événements à partir de la méthode héritée de BaseFixtures.
        $events = $this->retrieveEntities("event", $this);

        foreach ($events as $event) {
            // Ignore les événements info déjà entièrement lus par tous les utilisateurs.
            if ($event->getInfo() && $event->getInfo()->isFullyRead()) {
                continue;
            } else {
                // Récupère les informations relatives au jour, côté, et section.
                $day = $event->getDueDate();
                $side = $event->getSide();
                $section = $event->getSection()->getName();
                $createdAt = $updatedAt = $event->getCreatedAt();

                // Vérifie si le tag pour cet événement existe déjà.
                $tag = $this->em->getRepository(Tag::class)->findOneByDaySideSection($day, $side, $section);
                if (!$tag) {
                    // Si aucun tag n'est trouvé, un nouveau tag est créé.
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
                $this->em->flush(); // Sauvegarde le tag créé ou mis à jour.
                $this->updateTagCount($tag, $event); // Met à jour le compteur du tag en fonction de l'événement.
            }
        }
    }

    /**
     * Met à jour le compteur du tag selon le type d'événement.
     * 
     * @param Tag $tag Le tag à mettre à jour.
     * @param Event $event L'événement lié au tag.
     */
    private function updateTagCount(Tag $tag, Event $event): void
    {
        $type = $event->getType();
        // Si l'événement est de type "task", on met à jour le compteur des tâches, sinon on met à jour les informations.
        ($type === "task") ?
            $this->setTaskTagCount($tag, $event) :
            $this->setInfoTagCount($tag, $event);
    }

    /**
     * Met à jour le compteur des tags associés à une info pour chaque utilisateur non-lu.
     * 
     * @param Tag $tag Le tag à mettre à jour.
     * @param Event $event L'événement contenant les informations de l'info partagée.
     */
    private function setInfoTagCount(Tag $tag, Event $event): void
    {
        // Met à jour la date de modification du tag.
        $tag->setUpdatedAt($event->getUpdatedAt());

        // Récupère les utilisateurs avec lesquels l'info a été partagée mais non lue.
        $sharedWith = $event->getInfo()->getSharedWith();
        $users = [];
        foreach ($sharedWith as $userInfo) {
            if (!$userInfo->isRead()) {
                $users[] = $userInfo->getUser();
            }
        }

        // Pour chaque utilisateur, on vérifie si le tag info existe déjà et on l'actualise.
        foreach ($users as $user) {
            $tagInfo = $this->em->getRepository(TagInfo::class)->findOneByUserAndTag_info($user, $tag);
            if ($tagInfo) {
                $count = $tagInfo->getUnreadInfoCount();
                $tagInfo->setUnreadInfoCount($count + 1);
                $tagInfo->setUpdatedAt($event->getCreatedAt());
            } else {
                // Si le tag info n'existe pas, on en crée un nouveau et on l'ajoute au tag.
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
     * Met à jour le compteur des tags associés à une tâche pour chaque utilisateur.
     * 
     * @param Tag $tag Le tag à mettre à jour.
     * @param Event $event L'événement contenant la tâche.
     */
    private function setTaskTagCount(Tag $tag, Event $event): void
    {
        // Met à jour la date de modification du tag.
        $tag->setUpdatedAt($event->getUpdatedAt());

        // Récupère les utilisateurs associés à la tâche.
        $users = $event->getTask()->getSharedWith();

        // Met à jour ou crée un TagTask pour chaque utilisateur.
        foreach ($users as $user) {
            $tagTask = $this->em->getRepository(TagTask::class)->findOneByUserAndTag_task($user, $tag);

            if ($tagTask) {
                // Mise à jour du compteur si l'entité existe.
                $tagTask->setTagCount($tagTask->getTagCount() + 1);
                $tagTask->setUpdatedAt($event->getCreatedAt());
            } else {
                // Création d'une nouvelle entité TagTask si aucune association n'existe.
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

        $this->em->flush();
    }

    /**
     * Retourne la liste des fixtures dont cette fixture dépend.
     * 
     * @return array Liste des classes de fixtures dont celle-ci dépend.
     */
    public function getDependencies(): array
    {
        return [
            EventFixtures::class, // Dépend de EventFixtures pour que les événements existent avant de créer les tags.
        ];
    }
}
