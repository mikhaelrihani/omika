<?php

namespace App\Service\Event;

use App\Entity\Event\Event;
use App\Entity\Event\Tag;
use App\Entity\Event\TagInfo;
use App\Entity\Event\TagTask;
use App\Entity\User\User;
use App\Service\Event\EventService;
use App\Utils\ApiResponse;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class TagService
{
    protected DateTimeImmutable $now;
    public function __construct(
        protected EntityManagerInterface $em,
        protected EventService $eventService
    ) {
        $this->now = new DateTimeImmutable('today');
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Creates a new tag for the given event.
     * 
     * This method handles the creation of a tag associated with an event. It first creates the base tag using
     * `createTagBase` and then establishes the necessary relationships using `setTagRelation`.
     * 
     * @param Event $event The event for which the tag is being created.
     * @return Tag|ApiResponse Returns the created `Tag` entity if successful. Otherwise, returns an error response.
     */
    public function createTag(?Event $event): ?Tag
    {
        // Vérifier si l'événement est null ou invalide
        if ($event === null) {
            return null;
        }
        $tag = $this->createTagBase($event);
        $this->setTagRelation($event);
        return $tag;
    }

    /**
     * Creates a base tag for the given event.
     * 
     * This method creates a new tag entity based on the event's due date, side, and section.
     * If a tag already exists for the event, it updates the date status and active day.
     * 
     * @param Event $event The event for which the tag is being created.
     * @return Tag Returns the created or updated `Tag` entity.
     */
    private function createTagBase(Event $event): tag
    {
        // Vérifie si le tag pour cet événement existe déjà.
        $day = $event->getDueDate();
        $side = $event->getSide();
        $section = $event->getSection()->getName();
        $tag = $this->em->getRepository(Tag::class)->findOneByDaySideSection($day, $side, $section);
        if (!$tag) {
            $tag = (new Tag())
                ->setSection($section)
                ->setDay($event->getDueDate())
                ->setSide($event->getSide());
            $this->em->persist($tag);
        }
        $tag
            ->setDateStatus($event->getDateStatus())
            ->setActiveDay($event->getActiveDay());

        $this->em->flush();
        return $tag;
    }

    /**
     * Sets the relationship between the tag and users associated with the given event.
     * 
     * Depending on the type of the event (`task` or `info`), this method increments the appropriate
     * tag count for all shared users.
     * 
     * @param Event $event The event whose tag relationships need to be updated.
     * 
     */
    private function setTagRelation(Event $event): void
    {
        $type = $event->getType();
        $type === "task" ?
            $tag = $this->incrementSharedUsersTagTaskCount($event) :
            $tag = $this->incrementSharedUsersTagInfoCount($event);
    }

    /**
     * Finds a `Tag` entity associated with a given event.
     * 
     * This method searches for a `Tag` based on the event's due date, side, and section name.
     * If no tag is found, it returns an error response. Otherwise, it returns the matching `Tag` entity.
     * 
     * @param Event $event The event for which the tag is being searched.
     * 
     * @return ApiResponse|Tag Returns the `Tag` entity if found. Otherwise, returns a `ApiResponse` error response.
     * 
     * @throws \Exception If an error occurs during the search, it is handled and a meaningful error message is returned.
     */
    private function findTag($event): ApiResponse|Tag
    {
        $day = $event->getDueDate();
        $side = $event->getSide();
        $section = $event->getSection()->getName();
        // Trouver le tag pour l'événement en fonction de la date, du côté et de la section.
        $tag = $this->em->getRepository(Tag::class)->findOneByDaySideSection($day, $side, $section);
        if (!$tag) {
            return ApiResponse::error('Tag not found for the specified event.');
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
     * @return ApiResponse Returns a success response if the tag count is updated successfully.
     * 
     * @throws \InvalidArgumentException If the event type is invalid or the status is unsupported.
     */
    public function updateTagCountOnUserAction(Event $event, user $user = null)
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
            return ApiResponse::success('Tag count updated successfully.');
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while updating the tag count: ' . $e->getMessage());
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
     */
    public function createTagInfo(Tag $tag, user $user)
    {
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
     * @return ApiResponse Returns a success response if the operation is successful, or an error response if an exception occurs.
     * 
     * @throws \Exception If any error occurs during the process, it is caught and returned in the error response.
     */
    public function incrementOneUserTagInfoCount(Event $event, user $user): ApiResponse
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

            return ApiResponse::success('Tag info count updated successfully.');
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while setting info tag count: ' . $e->getMessage());
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
     * @return ApiResponse Returns a success response if the operation is successful, or an error response if an exception occurs.
     * 
     * @throws \Exception If any error occurs during the process, it is caught and returned in the error response.
     */
    public function incrementSharedUsersTagInfoCount(Event $event): ApiResponse
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

            return ApiResponse::success('Tag info count updated successfully.');
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while setting info tag count: ' . $e->getMessage());
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
     */
    public function createTagTask(Tag $tag, user $user)
    {
        $tagTask = new TagTask();
        $tagTask
            ->setUser($user)
            ->setTag($tag)
            ->setTagCount(1)
            ->setCreatedAt($this->now)
            ->setUpdatedAt($this->now);
        $this->em->persist($tagTask);
        $tag->addTagTask($tagTask);
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
     * @return ApiResponse Returns a success response if the operation is successful, or an error response if an exception occurs.
     * 
     * @throws \Exception If any error occurs during the process, it is caught and returned in the error response.
     */
    public function incrementOneUserTagTaskCount(Event $event, User $user): ApiResponse
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

            return ApiResponse::success('Tag task count updated successfully.');
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while setting task tag count: ' . $e->getMessage());
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
     * @return ApiResponse Returns a success response if the operation is successful, or an error response if an exception occurs.
     * 
     * @throws \Exception If any error occurs during the process, it is caught and returned in the error response.
     */
    public function incrementSharedUsersTagTaskCount(Event $event): ApiResponse
    {
        try {
            // Récupère les utilisateurs associés à la tâche.
            $users = $this->eventService->getUsers($event);

            // Met à jour ou crée un TagTask pour chaque utilisateur.
            foreach ($users as $user) {
                $this->incrementOneUserTagTaskCount($event, $user);
            }
            return ApiResponse::success('Tag task count updated successfully.');
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while setting task tag count: ' . $e->getMessage());
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
     * @return ApiResponse
     */
    public function decrementOneUserTagCountByOne(Event $event, User $user, $flush = true): ApiResponse
    {
        try {
            $tag = $this->findTag($event);
            $type = $event->getType();

            if ($type === "task") {
                // Trouver le TagTask associé à l'utilisateur et au tag.
                $tagTask = $this->em->getRepository(TagTask::class)->findOneByUserAndTag_task($user, $tag);
                if (!$tagTask) {
                    return ApiResponse::error('TagTask not found for the specified user and tag.');
                }

                // Décrémenter le compteur de tags (en s'assurant qu'il ne descende pas en dessous de zéro).
                $tagTask->setTagCount(max(0, $tagTask->getTagCount() - 1));
                $tagTask->setUpdatedAt($this->now);
            } elseif ($type === "info") {
                // Gérer TagInfo si le type n'est pas "task".
                $tagInfo = $this->em->getRepository(TagInfo::class)->findOneByUserAndTag_info($user, $tag);
                if (!$tagInfo) {
                    return ApiResponse::error('TagInfo not found for the specified user and tag.');
                }

                // Décrémenter le compteur d'info non lue (en s'assurant qu'il ne descende pas en dessous de zéro).
                $tagInfo->setUnreadInfoCount(max(0, $tagInfo->getUnreadInfoCount() - 1));
                $tagInfo->setUpdatedAt($this->now);
            }

            if ($flush) {
                $this->em->flush(); // Flush si explicitement demandé
            }

            return ApiResponse::success('Tag count decremented successfully.');
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while decrementing the tag counter: ' . $e->getMessage());
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
     * @return ApiResponse
     */
    public function decrementSharedUsersTagCountByOne(Event $event): ApiResponse
    {
        $users = $this->eventService->getUsers($event);
        try {
            foreach ($users as $user) {
                $this->decrementOneUserTagCountByOne($event, $user, false);
            }
            $this->em->flush(); // Flush une seule fois après avoir décrémenté pour tous les utilisateurs.
            return ApiResponse::success('Tag count decremented successfully for multiple users.');
        } catch (Exception $e) {
            return ApiResponse::error('An error occurred while decrementing the tag counter for multiple users: ' . $e->getMessage());
        }

    }

    //! --------------------------------------------------------------------------------------------


}
