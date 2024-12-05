<?php

namespace App\Service\Media;

use App\Entity\Media\Note;
use App\Utils\ApiResponse;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class CronService
{

    protected DateTimeImmutable $now;
    protected int $notesDeleted;

    public function __construct(
        protected EntityManagerInterface $em,

    ) {
        $this->notesDeleted = 0;
        $this->now = new DateTimeImmutable();
    }
    //! --------------------------------------------------------------------------------------------
    /**
     * Execute a series of cron job steps for managing notes.
     *
     * Steps include:
     * - Deleting old notes
     *
     * @return ApiResponse An API response with the results of the cron job execution.
     */
    public function load(): ApiResponse
    {
        $steps = [
            'deleteOldNotes' => fn() => $this->deleteOldNotes(),
        ];

        // Execute each step and handle exceptions
        foreach ($steps as $stepName => $step) {
            try {
                $step();
            } catch (Exception $e) {
                return ApiResponse::error(
                    "Step -{$stepName}- failed :" . $e->getMessage(),
                    null,
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }

        return ApiResponse::success(
            "Deleted old notes: {$this->notesDeleted}.",
            null,
            Response::HTTP_OK
        );
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Deletes notes older than one month.
     */
    public function deleteOldNotes(): void
    {
        $notes = $this->em->getRepository(Note::class)->findAll();

        $notes = array_filter($notes, function ($note) {
            return $note->getCreatedAt() < $this->now->modify('-1 month');
        });
        foreach ($notes as $note) {
            $this->em->remove($note);
            $this->notesDeleted++;
        }
        $this->em->flush();
    }

}