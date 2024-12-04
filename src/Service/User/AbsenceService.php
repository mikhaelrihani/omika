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


class AbsenceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JsonResponseBuilder $jsonResponseBuilder,
        private ValidatorService $validateService,
        private ParameterBagInterface $params,
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

}