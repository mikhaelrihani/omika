<?php

namespace App\Service;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
use App\Entity\Event\TagInfo;
use App\Entity\Event\TagTask;
use App\Entity\User\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class TagService
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected ResponseService $responseService // Injecter ResponseService pour l'utiliser dans toute la classe
    ) {
    }

    /**
     * Creates or updates a tag associated with the given event.
     *
     * This method checks if a tag already exists for the combination of `day`, `side`,
     * and `section` from the provided event. If the tag does not exist, it creates a new one.
     * After creating or retrieving the tag, the method updates its associated counters
     * based on the event data.
     *
     * Directly interacts with the database using a repository method to find the tag.
     *
     * @param Event $event The event used to create or update the tag.
     *
     * @return ResponseService Returns a success response if the tag is created or updated successfully.
     *                         If an exception occurs, returns an error response with the error details.
     *
     * @throws \Exception If an unexpected error occurs during the tag creation or update process.
     */
    public function createOrUpdateTag(Event $event): ResponseService
    {
        try {
            $day = $event->getDueDate();
            $side = $event->getSide();
            $section = $event->getSection()->getName();

            // Vérifie si le tag existe déjà
            $tag = $this->em->getRepository(Tag::class)->findOneByDaySideSection($day, $side, $section);
            if (!$tag) {
                // Si le tag n'existe pas, on le crée
                $tag = new Tag();
                $tag
                    ->setSection($section)
                    ->setDay($day)
                    ->setDateStatus($event->getDateStatus())
                    ->setActiveDay($event->getActiveDay())
                    ->setSide($side);

                $this->em->persist($tag);
                $this->em->flush();
            }

            $this->updateTagCount($tag, $event);

            return $this->responseService::success('Tag created and counter updated successfully.');
        } catch (\Exception $e) {
            return $this->responseService::error('An error occurred while creating the tag: ' . $e->getMessage(), null, 'TAG_CREATION_FAILED');
        }
    }

    /**
     * Met à jour le compteur du tag en fonction du type d'événement (task ou info).
     * 
     * @param Tag $tag L'entité Tag à mettre à jour.
     * @param Event $event L'événement contenant les informations nécessaires.
     * 
     * @return ResponseService
     */
    private function updateTagCount(Tag $tag, Event $event): ResponseService
    {
        try {
            $type = $event->getType();
            if ($type === "task") {
                $this->updateTagTaskCount($tag, $event);
            } else {
                $this->updateTagInfoCount($tag, $event);
            }

            return $this->responseService::success('Tag count updated successfully.');
        } catch (\Exception $e) {
            return $this->responseService::error('An error occurred while updating the tag count: ' . $e->getMessage(), null, 'TAG_COUNT_UPDATE_FAILED');
        }
    }

    /**
     * Met à jour le compteur des tags associés à une info pour chaque utilisateur.
     * 
     * @param Tag $tag L'entité Tag à mettre à jour.
     * @param Event $event L'événement contenant les informations de l'info partagée.
     * 
     * @return ResponseService
     */
    private function updateTagInfoCount(Tag $tag, Event $event): ResponseService
    {
        try {
            $tag->setUpdatedAt($event->getUpdatedAt());

            // Récupère les utilisateurs avec lesquels l'info a été partagée.
            $sharedWith = $event->getInfo()->getSharedWith();
            $users = [];
            foreach ($sharedWith as $userInfo) {
                $users[] = $userInfo->getUser();
            }

            // Pour chaque utilisateur, associer un tag info en vérifiant s'il existe déjà.
            foreach ($users as $user) {
                $tagInfo = $this->em->getRepository(TagInfo::class)->findOneByUserAndTag($user, $tag);
                if ($tagInfo) {
                    $count = $tagInfo->getUnreadInfoCount();
                    $tagInfo->setUnreadInfoCount($count + 1);
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

            return $this->responseService::success('Tag info count updated successfully.');
        } catch (\Exception $e) {
            return $this->responseService::error('An error occurred while setting info tag count: ' . $e->getMessage(), null, 'INFO_TAG_COUNT_UPDATE_FAILED');
        }
    }

    /**
     * Met à jour le compteur des tags associés à une tâche pour chaque utilisateur.
     * 
     * @param Tag $tag L'entité Tag à mettre à jour.
     * @param Event $event L'événement contenant les informations de la tâche.
     * 
     * @return ResponseService
     */
    private function updateTagTaskCount(Tag $tag, Event $event): ResponseService
    {
        try {
            $tag->setUpdatedAt($event->getUpdatedAt());

            // Récupère les utilisateurs associés à la tâche.
            $users = $event->getTask()->getSharedWith();

            // Met à jour ou crée un TagTask pour chaque utilisateur.
            foreach ($users as $user) {
                // Recherche une association existante entre le tag et l'utilisateur.
                $tagTask = $this->em->getRepository(TagTask::class)->findOneByUserAndTag_task($user, $tag);

                if ($tagTask) {
                    // Mise à jour du compteur si l'entité existe.
                    $tagTask->setTagCount($tagTask->getTagCount() + 1);
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

            $this->em->flush();

            return $this->responseService::success('Tag task count updated successfully.');
        } catch (\Exception $e) {
            return $this->responseService::error('An error occurred while setting task tag count: ' . $e->getMessage(), null, 'TASK_TAG_COUNT_UPDATE_FAILED');
        }
    }

    /**
     * Décrémente le compteur de tags de un pour un événement et un utilisateur donnés.
     *
     * @param Event $event L'événement pour lequel le compteur de tags doit être décrémenté.
     * @param User $user L'utilisateur associé au compteur de tags.
     * @param bool $flush Indique si l'entité doit être flushée après la mise à jour.
     *
     * @return ResponseService
     */
    public function decrementTagCounterByOne(Event $event, User $user, $flush = true): ResponseService
    {
        try {
            $day = $event->getDueDate();
            $side = $event->getSide();
            $section = $event->getSection()->getName();
            $type = $event->getType();

            // Trouver le tag pour l'événement en fonction de la date, du côté et de la section.
            $tag = $this->em->getRepository(Tag::class)->findOneByDaySideSection($day, $side, $section);
            if (!$tag) {
                return $this->responseService::error('Tag not found for the specified event.', null, 'TAG_NOT_FOUND');
            }

            if ($type === "task") {
                // Trouver le TagTask associé à l'utilisateur et au tag.
                $tagTask = $this->em->getRepository(TagTask::class)->findOneByUserAndTag_task($user, $tag);
                if (!$tagTask) {
                    return $this->responseService::error('TagTask not found for the specified user and tag.', null, 'TAGTASK_NOT_FOUND');
                }

                // Décrémenter le compteur de tags (en s'assurant qu'il ne descende pas en dessous de zéro).
                $tagTask->setTagCount(max(0, $tagTask->getTagCount() - 1));
                $tagTask->setUpdatedAt($event->getCreatedAt());
            } else {
                // Gérer TagInfo si le type n'est pas "task".
                $tagInfo = $this->em->getRepository(TagInfo::class)->findOneByUserAndTag_info($user, $tag);
                if (!$tagInfo) {
                    return $this->responseService::error('TagInfo not found for the specified user and tag.', null, 'TAGINFO_NOT_FOUND');
                }

                // Décrémenter le compteur d'info non lue (en s'assurant qu'il ne descende pas en dessous de zéro).
                $tagInfo->setUnreadInfoCount(max(0, $tagInfo->getUnreadInfoCount() - 1));
                $tagInfo->setUpdatedAt($event->getCreatedAt());
            }

            if ($flush) {
                $this->em->flush(); // Flush si explicitement demandé
            }

            return $this->responseService::success('Tag count decremented successfully.');
        } catch (\Exception $e) {
            return $this->responseService::error('An error occurred while decrementing the tag counter: ' . $e->getMessage(), null, 'TAG_DECREMENT_FAILED');
        }
    }
    /**
     * Deletes tags that are older than yesterday.
     *
     * This method identifies tags that have a `day` field corresponding to either
     * yesterday or the day before yesterday and deletes them from the database.
     * The operation directly interacts with the database using Doctrine's QueryBuilder
     * for optimal performance.
     *
     * @return ResponseService Returns a success message if the tags are deleted successfully,
     *                         or an error message if an exception occurs.
     *
     * @throws \Exception If an unexpected error occurs during the tag deletion process.
     */
    public function deletePastTag(): ResponseService
    {
        try {
            $yesterday = new DateTimeImmutable("yesterday");
            $dayBeforeYesterday = new DateTimeImmutable("yesterday -1 day");

            $tags = $this->em->createQueryBuilder()
                ->select('t')
                ->from(Tag::class, 't')
                ->where('t.day IN (:days)')
                ->setParameter('days', [$dayBeforeYesterday, $yesterday])
                ->getQuery()
                ->getResult();

            foreach ($tags as $tag) {
                $this->em->remove($tag);
            }

            $this->em->flush();

            return $this->responseService::success('Past tags deleted successfully.');
        } catch (\Exception $e) {
            return $this->responseService::error(
                'An error occurred while deleting past tags: ' . $e->getMessage(),
                null,
                'TAG_DELETION_FAILED'
            );
        }
    }

    /**
     * Updates the tag count after a task event is marked as completed.
     *
     * This method checks if the event is of type `task` and its associated task's
     * status is `done`. If true, it decrements the tag counter for all users
     * associated with the task.
     *
     * @param Event $event The event whose task completion triggers the tag count update.
     *
     * @return ResponseService Returns a success response if the tag count was updated successfully.
     *                         Returns an error response with details if an exception occurs.
     *
     * @throws \Exception If an error occurs during the tag count update process.
     */
    public function updateTagCountAfterEventTaskCompleted(Event $event): ResponseService
    {
        try {
            if ($event->getType() === 'task' && $event->getTask()->getTaskStatus() === 'done') {
                $users = $event->getTask()->getSharedWith();
                foreach ($users as $user) {
                    $this->decrementTagCounterByOne($event, $user, true);
                }
            }
            return $this->responseService::success('Tag count updated after task completion.');
        } catch (\Exception $e) {
            return $this->responseService::error(
                'An error occurred while updating tag count after task completion: ' . $e->getMessage(),
                null,
                'TAG_COUNT_UPDATE_FAILED'
            );
        }
    }


    /**
     * Updates the tag count after an info event is read by a single user.
     *
     * This method checks if the event is of type `info`. If true, it decrements
     * the tag counter for the specified user associated with the info event.
     *
     * @param Event $event The event whose information is read by the user.
     * @param User $user The user who read the info event.
     *
     * @return ResponseService Returns a success response if the tag count was updated successfully.
     *                         Returns an error response with details if an exception occurs.
     *
     * @throws \Exception If an error occurs during the tag count update process.
     */
    public function updateTagCountAfterEventInfoIsReadByOneUser(Event $event, User $user): ResponseService
    {
        try {
            if ($event->getType() === 'info') {
                $this->decrementTagCounterByOne($event, $user, true);
            }
            return $this->responseService::success('Tag count updated after info is read.');
        } catch (\Exception $e) {
            return $this->responseService::error(
                'An error occurred while updating tag count after info is read: ' . $e->getMessage(),
                null,
                'TAG_COUNT_UPDATE_FAILED'
            );
        }
    }

}
