<?php

namespace App\Service\User;

use App\Entity\User\Absence;
use App\Utils\ApiResponse;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CronService
{

    protected DateTimeImmutable $now;
    protected int $absencesDeleted;
    protected int $absencesUpdated;

    public function __construct(
        protected EntityManagerInterface $em,

    ) {
        $this->absencesDeleted = 0;
        $this->absencesUpdated = 0;
        $this->now = new DateTimeImmutable();
    }
    //! --------------------------------------------------------------------------------------------
    /**
     * Execute a series of cron job steps for managing absences.
     *
     * Steps include:
     * - Deleting old absences
     * - Updating the status of active/inactive absences
     *
     * @return ApiResponse An API response with the results of the cron job execution.
     */
    public function load(): ApiResponse
    {
        $steps = [
            'deleteAbsences' => fn() => $this->deleteOldAbsences(),
            'updateAbsences' => fn() => $this->updateAbsencesStatus()
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
            "Deleted Absences: {$this->absencesDeleted}, Updated Absences: {$this->absencesUpdated}.",
            null,
            Response::HTTP_OK
        );
    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Update the status of absences based on their start and end dates.
     *
     * - Sets status to 'active' if the current date is within the absence period.
     * - Sets status to 'inactive' if the absence period has ended or is in the future.
     *
     * @return void
     */
    public function updateAbsencesStatus(): void
    {
        $absences = $this->em->getRepository(Absence::class)->findAll();

        foreach ($absences as $absence) {
            $currentStatus = $absence->getStatus();
            $isActive = $this->now >= $absence->getStartDate() && $this->now <= $absence->getEndDate();
            $absence->setStatus($isActive ? 'active' : 'inactive');

            $updatedStatus = $absence->getStatus();
            if ($updatedStatus !== $currentStatus) {
                $this->absencesUpdated++;
            }
        }
        $this->em->flush();
    }
    //! --------------------------------------------------------------------------------------------

    /**
     * Delete absences older than one month from the current date.
     *
     * - Filters absences to find those that ended more than one month ago.
     * - Removes these absences from the database.
     *
     * @return void
     */
    public function deleteOldAbsences(): void
    {
        $absences = $this->em->getRepository(Absence::class)->findAll();

        $absences = array_filter($absences, function ($absence) {
            return $absence->getEndDate() < $this->now->modify('-1 month');
        });
        foreach ($absences as $absence) {
            $this->em->remove($absence);
            $this->absencesDeleted++;
        }
        $this->em->flush();
    }

}