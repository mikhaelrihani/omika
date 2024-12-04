<?php

namespace App\Service\User;


use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User\Contact;
use App\Entity\User\User;
use App\Repository\User\AbsenceRepository;
use DateTimeImmutable;

class AbsenceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JsonResponseBuilder $jsonResponseBuilder,
        private ValidatorService $validateService,
        private ParameterBagInterface $params,
        private AbsenceRepository $absenceRepository
    ) {
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Updates the late count for a specific entity (Contact or User) by incrementing or decrementing it.
     *
     * @param int    $id          The ID of the entity to update.
     * @param string $entityName  The name of the entity, either "contact" or "user".
     * @param bool   $late        Indicates whether to increment (true) or decrement (false) the late count.
     *
     * @return ApiResponse Returns an ApiResponse indicating the success or failure of the operation.
     *
     * @throws \Doctrine\ORM\EntityNotFoundException If the entity is not found in the database.
     */
    public function updateLateCount(int $id, string $entityName, bool $late): ApiResponse
    {
        $entity = ($entityName = "contact") ?
            $this->em->getRepository(Contact::class)->find($id) :
            $this->em->getRepository(User::class)->find($id);

        if (!$entity) {
            return ApiResponse::error("There is no $entityName with this id", null, Response::HTTP_BAD_REQUEST);
        }
        $late ?
            $entity->setLateCount($entity->getLateCount() + 1) :
            $entity->setLateCount(max(0, $entity->getLateCount() - 1));

        $this->em->flush();
        return ApiResponse::success(ucfirst($entityName) . " property late_count updated successfully", [$entityName => $entity], Response::HTTP_OK);
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieve absences based on the status, entity type (user/contact), and date.
     *
     * @param array $data Contains the following keys:
     *                    - string $data[0]: The entity type ('user' or 'contact').
     *                    - int $data[1]: The ID of the entity (user or contact).
     *                    - string $data[2]: The date in 'Y-m-d' format.
     * @return ApiResponse
     * @throws \Exception If an unexpected error occurs during database interaction.
     */
    public function getAbsences(string $date): ApiResponse
    {
        try {
            $absences = $this->absenceRepository->findByStatusAndDate($date);

            return ApiResponse::success("Absences retrieved successfully", ["absences" => $absences], Response::HTTP_OK);
        } catch (\Exception $e) {
            return ApiResponse::error(
                "Failed to retrieve absences: " . $e->getMessage(),
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }



    //! --------------------------------------------------------------------------------------------

    public function newAbsence()
    {

    }

    //! --------------------------------------------------------------------------------------------

    public function updateAbsence()
    {

    }

    //! --------------------------------------------------------------------------------------------

    public function deleteAbsence()
    {

    }
}