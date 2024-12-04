<?php

namespace App\Service\User;


use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;


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

    public function updateLateCount(int $id, string $entityName, bool $late = true): ApiResponse
    {
        $className = ucfirst($entityName);
        $entity = $this->em->getRepository($className::class)->find($id);
        if (!$entity) {
            return ApiResponse::error("There is no $entityName with this id", null, Response::HTTP_BAD_REQUEST);
        }
        $late ?
            $entity->setLateCount($entity->getLateCount() + 1) :
            $entity->setLateCount($entity->getLateCount() - 1);

        $this->em->flush();
        return ApiResponse::success("$entityName late count updated successfully", [(string) $entityName => $entity], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

}