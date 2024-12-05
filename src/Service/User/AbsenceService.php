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

    /**
     * Creates a new absence record and associates it with a user or contact.
     *
     * This method validates the JSON request data, creates an `Absence` entity, 
     * determines its status based on the current date, and associates it with 
     * the appropriate user or contact. Finally, it persists the new entity to the database.
     *
     * @param Request $request The HTTP request containing the data for the new absence.
     *
     * @return ApiResponse Returns an `ApiResponse` indicating success or failure. 
     *                     On success, includes the newly created `Absence` entity.
     *
     * @throws \Exception Throws an exception if the request data is invalid or if the 
     *                    absence cannot be associated with a valid user or contact.
     */
    public function newAbsence(Request $request)
    {
        $responseData = $this->validateService->validateJson($request);
        if (!$responseData->isSuccess()) {
            return ApiResponse::error($responseData->getMessage(), null, $responseData->getStatusCode());
        }

        $absence = (new Absence())
            ->setStartDate(new DateTimeImmutable($responseData->getData()[ 'startDate' ]))
            ->setEndDate(new DateTimeImmutable($responseData->getData()[ 'endDate' ]))
            ->setReason($responseData->getData()[ 'reason' ])
            ->setPlanningUpdate(0)
            ->setAuthor($this->CurrentUser->getCurrentUser()->getFullName());

        // Determine the absence status based on the current date.
        $isActive = $this->now >= $absence->getStartDate() && $this->now <= $absence->getEndDate();
        $absence->setStatus($isActive ? 'active' : 'inactive');

        // Determine the absence type and find the corresponding user or contact.
        $type = $responseData->getData()[ 'type' ];
        $absent = ($type === 'user') ?
            $this->em->getRepository(User::class)->find($responseData->getData()[ 'id' ]) :
            $this->em->getRepository(Contact::class)->find($responseData->getData()[ 'id' ]);

        if (!$absent) {
            return ApiResponse::error("No $type found", null, Response::HTTP_NOT_FOUND);
        }

        // Associate the absence with the user or contact.
        $absent->addAbsence($absence);

        $responseValidation = $this->validateService->validateEntity($absence);
        if (!$responseValidation->isSuccess()) {
            return ApiResponse::error($responseValidation->getMessage(), null, $responseValidation->getStatusCode());
        }

        // Persist the absence entity and flush changes to the database.
        $this->em->persist($absence);
        $this->em->flush();

        return ApiResponse::success("Absence created successfully", ["absence" => $absence], Response::HTTP_CREATED);
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

        $absence->setAuthor($this->CurrentUser->getCurrentUser()->getFullName());

        $responseValidation = $this->validateService->validateEntity($absence);
        if (!$responseValidation->isSuccess()) {
            return ApiResponse::error($responseValidation->getMessage(), null, $responseValidation->getStatusCode());
        }

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