<?php

namespace App\Service\User;

use App\Entity\User\Absence;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User\Contact;
use App\Entity\User\User;
use App\Repository\User\AbsenceRepository;
use App\Utils\CurrentUser;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class AbsenceService
{
    protected DateTimeImmutable $now;
    public function __construct(
        private EntityManagerInterface $em,
        private JsonResponseBuilder $jsonResponseBuilder,
        private ValidatorService $validateService,
        private ParameterBagInterface $params,
        private AbsenceRepository $absenceRepository,
        protected SerializerInterface $serializer,
        protected CurrentUser $CurrentUser
    ) {
        $this->now = new DateTimeImmutable();
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

    /**
     * Updates an existing absence entity based on the provided JSON payload.
     *
     * @param int     $id      The ID of the absence to update.
     * @param Request $request The HTTP request containing the JSON payload with the updated absence data.
     *
     * @return ApiResponse Returns an ApiResponse object indicating the success or failure of the operation.
     */
    public function updateAbsence(int $id, Request $request): ApiResponse
    {
        $responseData = $this->validateService->validateJson($request);
        if (!$responseData->isSuccess()) {
            return ApiResponse::error($responseData->getMessage(), null, $responseData->getStatusCode());
        }

        $absence = $this->absenceRepository->find($id);
        if (!$absence) {
            return ApiResponse::error("No absence found", null, Response::HTTP_NOT_FOUND);
        }

        $absence = $this->serializer->deserialize(
            $request->getContent(),
            Absence::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $absence]
        );

        if (!$absence) {
            return ApiResponse::error("Failed to populate object absence", null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $isActive = $this->now >= $absence->getStartDate() && $this->now <= $absence->getEndDate();
        $absence->setStatus($isActive ? 'active' : 'inactive');

        $author = $this->CurrentUser->getCurrentUser()->getFullName();
        $absence->setAuthor($author);
        $this->em->flush();
        return ApiResponse::success("Absence updated successfully", ["absence" => $absence], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Deletes an existing absence entity by its ID.
     *
     * @param int $id The ID of the absence to delete.
     *
     * @return ApiResponse Returns an ApiResponse object indicating the success or failure of the operation.
     *
     * @throws \Exception If the deletion process fails due to database errors or constraints.
     */
    public function deleteAbsence(int $id): ApiResponse
    {
        $absence = $this->absenceRepository->find($id);
        if (!$absence) {
            return ApiResponse::error("No absence found", null, Response::HTTP_NOT_FOUND);
        }

        try {
            // Remove the absence from associated user and contact relationships
            if ($absence->getUser() !== null) {
                $absence->getUser()->removeAbsence($absence);
            }

            if ($absence->getContact() !== null) {
                $absence->getContact()->removeAbsence($absence);
            }

            $this->em->remove($absence);
            $this->em->flush();
            return ApiResponse::success("Absence deleted successfully", null, Response::HTTP_OK);
        } catch (\Exception $e) {
            return ApiResponse::error("Failed to delete absence: " . $e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}