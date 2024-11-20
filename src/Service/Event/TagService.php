<?php

namespace App\Service;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
use App\Entity\Event\TagInfo;
use App\Entity\Event\TagTask;
use App\Entity\User\User;
use App\Service\Event\EventService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class TagService
{
    protected DateTimeImmutable $now;
    public function __construct(
        protected EntityManagerInterface $em,
        protected ResponseService $responseService,
        protected EventService $eventService
    ) {
        $this->now = DateTimeImmutable::createFromFormat('Y-m-d ', (new DateTimeImmutable())->format('Y-m-d'));
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Creates a new tag for the given event.
     * 
     * This method handles the creation of a tag associated with an event. It first creates the base tag using
     * `createTagBase` and then establishes the necessary relationships using `setTagRelation`.
     * 
     * @param Event $event The event for which the tag is being created.
     * 
     * @return ResponseService Returns a success response if the tag is created successfully or an error response in case of failure.
     * 
     * @throws Exception If any error occurs during the tag creation process, it is caught and returned in the error response.
     */
    public function createTag(Event $event): ResponseService
    {
        try {
            $this->createTagBase($event);
            $this->setTagRelation($event);
            return $this->responseService::success('Tag created successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while creating the tag: ' . $e->getMessage(), null, 'TAG_CREATION_FAILED');
        }
    }

    /**
     * Creates the base tag entity for the given event.
     * 
     * This method initializes and persists a `Tag` entity based on the details of the provided event,
     * including section, due date, date status, active day, and side.
     * 
     * @param Event $event The event for which the base tag is being created.
     * 
     * @return ResponseService Returns a success response if the tag is created successfully or an error response in case of failure.
     * 
     * @throws Exception If any error occurs during the tag creation process, it is caught and returned in the error response.
     */
    public function createTagBase(Event $event): ResponseService
    {
        try {
            $tag = new Tag();
            $tag
                ->setSection($event->getSection()->getName())
                ->setDay($event->getDueDate())
                ->setDateStatus($event->getDateStatus())
                ->setActiveDay($event->getActiveDay())
                ->setSide($event->getSide());

            $this->em->persist($tag);
            $this->em->flush();
            return $this->responseService::success('Tag created successfully.');
        } catch (Exception $e) {
            return $this->responseService::error($e->getMessage());
        }
    }

    /**
     * Sets the relationship between the tag and users associated with the given event.
     * 
     * Depending on the type of the event (`task` or `info`), this method increments the appropriate
     * tag count for all shared users.
     * 
     * @param Event $event The event whose tag relationships need to be updated.
     * 
     * @return ResponseService Returns a success response if the operation is successful or an error response in case of failure.
     * 
     * @throws Exception If any error occurs during the process, it is caught and returned in the error response.
     */
    public function setTagRelation(Event $event): ResponseService
    {
        try {
            $type = $event->getType();
            $type === "task" ?
                $this->incrementSharedUsersTagTaskCount($event) :
                $this->incrementSharedUsersTagInfoCount($event);

            return $this->responseService::success('Tag count updated successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while updating the tag count: ' . $e->getMessage(), null, 'TAG_COUNT_UPDATE_FAILED');
        }
    }

    /**
     * Finds a `Tag` entity associated with a given event.
     * 
     * This method searches for a `Tag` based on the event's due date, side, and section name.
     * If no tag is found, it returns an error response. Otherwise, it returns the matching `Tag` entity.
     * 
     * @param Event $event The event for which the tag is being searched.
     * 
     * @return ResponseService|Tag Returns the `Tag` entity if found. Otherwise, returns a `ResponseService` error response.
     * 
     * @throws \Exception If an error occurs during the search, it is handled and a meaningful error message is returned.
     */
    public function findTag($event): ResponseService|Tag
    {
        $day = $event->getDueDate();
        $side = $event->getSide();
        $section = $event->getSection()->getName();
        // Trouver le tag pour l'événement en fonction de la date, du côté et de la section.
        $tag = $this->em->getRepository(Tag::class)->findOneByDaySideSection($day, $side, $section);
        if (!$tag) {
            return $this->responseService::error('Tag not found for the specified event.', null, 'TAG_NOT_FOUND');
        }
        return $tag;
    }

    /**
     * Updates the tag count based on a user's action on an event.
     * 
     * This method adjusts the tag counters for tasks or info events depending on the user's interaction. 
     * For tasks, the adjustment depends on the provided status (`todo`, `done`, `pending`). 
     * For info events, it decreases the user's tag count when the event is accessed.
     * 
     * @param Event $event The event associated with the user's action.
     * @param User|null $user (Optional) The user performing the action, required for info events.
     * 
     * @return ResponseService Returns a success response if the tag count is updated successfully.
     * 
     * @throws \InvalidArgumentException If the event type is invalid or the status is unsupported.
     */
    public function updateTagCountOnUserAction(Event $event, user $user = null): ResponseService
    {
        try {
            if ($event->getType() === "task") {
                $status = $event->getTask()->getTaskStatus();
                match ($status) {
                    // the user ticked the task as done from a previous status (todo,pending,late)
                    "todo" => $this->incrementSharedUsersTagTaskCount($event),
                    // the user ticked back the task from done to todo
                    "done" => $this->decrementSharedUsersTagCountByOne($event),
                    // the user ticked back the task status from done to pending
                    "pending" => $this->decrementSharedUsersTagCountByOne($event),
                    default => null
                };

            } else if ($event->getType() === "info") {
                // if the user opened the event info page
                $this->decrementOneUserTagCountByOne($event, $user);
            }
            return $this->responseService::success('Tag count updated successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while updating the tag count: ' . $e->getMessage(), null, 'TAG_COUNT_UPDATE_FAILED');
        }
    }

     /**
     * Deletes tags that are older than yesterday.
     * we delete past tags because they are no longer relevant , tags are made to inform about the current day events or future events only.
     * This method identifies tags that have a `day` field corresponding to either
     * yesterday or the day before yesterday and deletes them from the database.
     * The operation directly interacts with the database using Doctrine's QueryBuilder
     * for optimal performance.
     *
     * @return ResponseService Returns a success message if the tags are deleted successfully,
     *                         or an error message if an exception occurs.
     *
     * @throws Exception If an unexpected error occurs during the tag deletion process.
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
        } catch (Exception $e) {
            return $this->responseService::error(
                'An error occurred while deleting past tags: ' . $e->getMessage(),
                null,
                'TAG_DELETION_FAILED'
            );
        }
    }

    //! --------------------------------------------------------------------------------------------
    /**
     * Creates a new `TagInfo` entity and associates it with a `Tag` and a `User`.
     * 
     * This method initializes a `TagInfo` object with a default unread information count of 1,
     * associates it with the specified `Tag` and `User`, and persists it to the database.
     * 
     * @param Tag $tag The tag to which the `TagInfo` will be linked.
     * @param User $user The user associated with the `TagInfo`.
     * 
     * @return ResponseService Returns a success response if the `TagInfo` is created successfully.
     *                        Returns an error response if an exception occurs during the process.
     * 
     * @throws \Exception If an error occurs while persisting the `TagInfo`, it is caught and an error response is returned.
     */
    public function createTagInfo(Tag $tag, user $user): ResponseService
    {
        try {
            $tagInfo = new TagInfo();
            $tagInfo
                ->setUser($user)
                ->setTag($tag)
                ->setUnreadInfoCount(1)
                ->setCreatedAt($this->now)
                ->setUpdatedAt($this->now);
            $this->em->persist($tagInfo);
            $tag->addTagInfo($tagInfo);
            $this->em->flush();
            return $this->responseService::success('Tag info created successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while creating tag info: ' . $e->getMessage(), null, 'TAG_INFO_CREATION_FAILED');
        }
    }

    /**
     * Increments the unread info count for a specific user and tag.
     * 
     * If a `TagInfo` record already exists for the user and tag, the method increments the unread info count and updates the timestamps.
     * If no record exists, it creates a new one and sets the unread info count to 1.
     * 
     * @param Event $event The event containing the shared information.
     * @param User $user The user for whom the tag info count is updated.
     * 
     * @return ResponseService Returns a success response if the operation is successful, or an error response if an exception occurs.
     * 
     * @throws \Exception If any error occurs during the process, it is caught and returned in the error response.
     */
    public function incrementOneUserTagInfoCount(Event $event, user $user): ResponseService
    {
        try {
            $tag = $this->findTag($event);
            $tagInfo = $this->em->getRepository(TagInfo::class)->findOneByUserAndTag($user, $tag);
            if ($tagInfo) {
                $count = $tagInfo->getUnreadInfoCount();
                $tagInfo->setUnreadInfoCount($count + 1);
                $tagInfo->setUpdatedAt($this->now);
                $this->em->flush();
            } else {
                $this->createTagInfo($tag, $user);
            }

            return $this->responseService::success('Tag info count updated successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while setting info tag count: ' . $e->getMessage(), null, 'INFO_TAG_COUNT_UPDATE_FAILED');
        }
    }

    /**
     * Increments the unread info count for all users associated with the event's shared information.
     * 
     * For each user, the method updates or creates a `TagInfo` record linked to the tag and user,
     * ensuring the unread info count is incremented.
     * 
     * @param Event $event The event containing the shared information.
     * 
     * @return ResponseService Returns a success response if the operation is successful, or an error response if an exception occurs.
     * 
     * @throws \Exception If any error occurs during the process, it is caught and returned in the error response.
     */
    public function incrementSharedUsersTagInfoCount(Event $event): ResponseService
    {
        try {
            // Récupère les utilisateurs avec lesquels l'info a été partagée.
            $sharedWith = $event->getInfo()->getSharedWith();
            $users = [];
            foreach ($sharedWith as $userInfo) {
                $users[] = $userInfo->getUser();
            }

            // Pour chaque utilisateur, associer un tag info en vérifiant s'il existe déjà.
            foreach ($users as $user) {
                $this->incrementOneUserTagInfoCount($event, $user);
            }

            return $this->responseService::success('Tag info count updated successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while setting info tag count: ' . $e->getMessage(), null, 'INFO_TAG_COUNT_UPDATE_FAILED');
        }
    }

    //! --------------------------------------------------------------------------------------------
    /**
     * Creates a new `TagTask` entity and associates it with a `Tag` and a `User`.
     * 
     * This method initializes a `TagTask` object with a default task count of 1,
     * associates it with the specified `Tag` and `User`, and persists it to the database.
     * 
     * @param Tag $tag The tag to which the `TagTask` will be linked.
     * @param User $user The user associated with the `TagTask`.
     * 
     * @return ResponseService Returns a success response if the `TagTask` is created successfully.
     *                        Returns an error response if an exception occurs during the process.
     * 
     * @throws \Exception If an error occurs while persisting the `TagTask`, it is caught and an error response is returned.
     */
    public function createTagTask(Tag $tag, user $user): ResponseService
    {
        try {
            $tagTask = new TagTask();
            $tagTask
                ->setUser($user)
                ->setTag($tag)
                ->setTagCount(1)
                ->setCreatedAt($this->now)
                ->setUpdatedAt($this->now);
            $this->em->persist($tagTask);
            $tag->addTagTask($tagTask);
            $this->em->flush();
            return $this->responseService::success('Tag task created successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while creating tag task: ' . $e->getMessage(), null, 'TAG_TASK_CREATION_FAILED');
        }
    }

    /**
     * Increments the task count for a specific user and tag.
     * 
     * If a `TagTask` record already exists for the user and tag, the method increments the task count and updates the timestamps.
     * If no record exists, it creates a new one and sets the task count to 1.
     * 
     * @param Event $event The event containing the task information.
     * @param User $user The user for whom the tag task count is updated.
     * 
     * @return ResponseService Returns a success response if the operation is successful, or an error response if an exception occurs.
     * 
     * @throws \Exception If any error occurs during the process, it is caught and returned in the error response.
     */
    public function incrementOneUserTagTaskCount(Event $event, User $user): ResponseService
    {
        try {
            $tag = $this->findTag($event);
            // Recherche une association existante entre le tag et l'utilisateur.
            $tagTask = $this->em->getRepository(TagTask::class)->findOneByUserAndTag_task($user, $tag);

            if ($tagTask) {
                // Mise à jour du compteur si l'entité existe.
                $tagTask->setTagCount($tagTask->getTagCount() + 1);
                $tagTask->setUpdatedAt($this->now);
                $this->em->flush();
            } else {
                // Création d'une nouvelle entité si aucune association n'existe.
                $this->createTagTask($tag, $user);
            }

            return $this->responseService::success('Tag task count updated successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while setting task tag count: ' . $e->getMessage(), null, 'TASK_TAG_COUNT_UPDATE_FAILED');
        }
    }

    /**
     * Increments the task count for all users associated with the event's shared task.
     * 
     * For each user, the method updates or creates a `TagTask` record linked to the tag and user,
     * ensuring the task count is incremented.
     * 
     * @param Event $event The event containing the shared task information.
     * 
     * @return ResponseService Returns a success response if the operation is successful, or an error response if an exception occurs.
     * 
     * @throws \Exception If any error occurs during the process, it is caught and returned in the error response.
     */
    public function incrementSharedUsersTagTaskCount(Event $event): ResponseService
    {
        try {
            // Récupère les utilisateurs associés à la tâche.
            $users = $this->eventService->getUsers($event);

            // Met à jour ou crée un TagTask pour chaque utilisateur.
            foreach ($users as $user) {
                $this->incrementOneUserTagTaskCount($event, $user);
            }
            return $this->responseService::success('Tag task count updated successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while setting task tag count: ' . $e->getMessage(), null, 'TASK_TAG_COUNT_UPDATE_FAILED');
        }
    }

    //! --------------------------------------------------------------------------------------------
    /**
     * Décrémente le compteur de tags de un pour un événement et un utilisateur donnés.
     *
     * @param Event $event L'événement pour lequel le compteur de tags doit être décrémenté.
     * @param User $user L'utilisateur associé au compteur de tags.
     * @param bool $flush Indique si l'entité doit être flushée après la mise à jour.
     *
     * @return ResponseService
     */
    public function decrementOneUserTagCountByOne(Event $event, User $user, $flush = true): ResponseService
    {
        try {
            $tag = $this->findTag($event);
            $type = $event->getType();

            if ($type === "task") {
                // Trouver le TagTask associé à l'utilisateur et au tag.
                $tagTask = $this->em->getRepository(TagTask::class)->findOneByUserAndTag_task($user, $tag);
                if (!$tagTask) {
                    return $this->responseService::error('TagTask not found for the specified user and tag.', null, 'TAGTASK_NOT_FOUND');
                }

                // Décrémenter le compteur de tags (en s'assurant qu'il ne descende pas en dessous de zéro).
                $tagTask->setTagCount(max(0, $tagTask->getTagCount() - 1));
                $tagTask->setUpdatedAt($this->now);
            } elseif ($type === "info") {
                // Gérer TagInfo si le type n'est pas "task".
                $tagInfo = $this->em->getRepository(TagInfo::class)->findOneByUserAndTag_info($user, $tag);
                if (!$tagInfo) {
                    return $this->responseService::error('TagInfo not found for the specified user and tag.', null, 'TAGINFO_NOT_FOUND');
                }

                // Décrémenter le compteur d'info non lue (en s'assurant qu'il ne descende pas en dessous de zéro).
                $tagInfo->setUnreadInfoCount(max(0, $tagInfo->getUnreadInfoCount() - 1));
                $tagInfo->setUpdatedAt($this->now);
            }

            if ($flush) {
                $this->em->flush(); // Flush si explicitement demandé
            }

            return $this->responseService::success('Tag count decremented successfully.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while decrementing the tag counter: ' . $e->getMessage(), null, 'TAG_DECREMENT_FAILED');
        }
    }

    /**
     * Decrements the tag counter by one for multiple users associated with a given event.
     *
     * This method iterates through the provided collection of users and applies the
     * `decrementTagCounterByOne` method to each user in relation to the specified event.
     *
     * @param User[] $users An array or iterable of User entities for whom the tag counter will be decremented.
     * @param Event $event The event associated with the tag counter update.
     *
     * @return ResponseService
     */
    public function decrementSharedUsersTagCountByOne(Event $event): ResponseService
    {
        $users = $this->eventService->getUsers($event);
        try {
            foreach ($users as $user) {
                $this->decrementOneUserTagCountByOne($event, $user, false);
            }
            $this->em->flush(); // Flush une seule fois après avoir décrémenté pour tous les utilisateurs.
            return $this->responseService::success('Tag count decremented successfully for multiple users.');
        } catch (Exception $e) {
            return $this->responseService::error('An error occurred while decrementing the tag counter for multiple users: ' . $e->getMessage(), null, 'TAG_DECREMENT_FAILED');
        }

    }

    //! --------------------------------------------------------------------------------------------

    
}
