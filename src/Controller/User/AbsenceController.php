<?php

namespace App\Controller\User;

use App\Repository\User\AbsenceRepository;
use App\Repository\User\UserLoginRepository;
use App\Repository\User\UserRepository;
use App\Service\Media\FileService;
use App\Service\User\AbsenceService;
use App\Service\User\UserService;
use App\Service\ValidatorService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('api/absence', name: "app_absence_")]
class AbsenceController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserLoginRepository $userLoginRepository,
        private EntityManagerInterface $em,
        public UserService $userService,
        private ValidatorService $validateService,
        private FileService $fileService,
        private AbsenceService $absenceService,
        private AbsenceRepository $absenceRepository
    ) {
    }

    //! --------------------------------------------------------------------------------------------
    /**
     * Updates the late count for a specified entity (Contact or User) based on a provided JSON payload.
     *
     * @Route("/updateLateCount/{entityName}/{id}", name="updateLateCount", methods="PUT")
     *
     * @param int       $id          The ID of the entity to update.
     * @param string    $entityName  The name of the entity, either "contact" or "user".
     * @param Request   $request     The HTTP request containing the JSON payload with the "late" key.
     *
     * @return JsonResponse A JSON response indicating the success or failure of the operation.
     *
     * The request payload must be a valid JSON object with the following structure:
     * ```json
     * {
     *     "late": true // or false
     * }
     * ```
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException If the request validation fails.
     */
    #[Route('/updateLateCount/{entityName}/{id}', name: 'updateLateCount', methods: 'PUT')]
    public function updateLateCount(int $id, string $entityName, Request $request): JsonResponse
    {
        $responseData = $this->validateService->validateJson($request);
        if (!$responseData->isSuccess()) {
            return $this->json($responseData->getMessage(), $responseData->getStatusCode());
        }

        $responseLate = $this->absenceService->updateLateCount($id, $entityName, $responseData->getData()[ "late" ]);
        return $this->json($responseLate->getMessage(), $responseLate->getStatusCode());
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieve an absence by its ID.
     *
     * @Route("/getAbsence/{id}", name="getAbsence", methods={"GET"})
     *
     * @param int $id The ID of the absence to retrieve.
     * @return JsonResponse A JSON response containing the absence or an error message if not found.
     */
    #[Route("/getAbsence/{id}", name: "getAbsence", methods: ["GET"])]
    public function getAbsence(int $id)
    {
        $absence = $this->absenceRepository->find($id);
        if (!$absence) {
            return $this->json("No absence found", Response::HTTP_NOT_FOUND);
        }
        return $this->json(["message" => "Absence retrieved successfully", "absence" => $absence], Response::HTTP_OK, [], ['groups' => 'absence']);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieve all active absences for a specific date.
     *
     * @Route("/getActiveAbsencesByDate", name="getAbsencesByDate", methods={"POST"})
     *
     * @param Request $request The HTTP request containing the date in JSON format.
     * @return JsonResponse A JSON response containing the list of active absences or an error message.
     */
    #[Route('/getActiveAbsencesByDate', name: 'getAbsencesByDate', methods: 'POST')]
    public function getActiveAbsencesByDate(Request $request)
    {
        $responseData = $this->validateService->validateJson($request);
        if (!$responseData->isSuccess()) {
            return $this->json($responseData->getMessage(), $responseData->getStatusCode());
        }

        (string) $date = $responseData->getData()[ "date" ];

        $responseAbsences = $this->absenceService->getAbsences($date);
        if (!$responseAbsences->isSuccess()) {
            return $this->json($responseAbsences->getMessage(), $responseAbsences->getStatusCode());
        }

        $Absences = $responseAbsences->getData()[ "absences" ];
        return $this->json(
            ["message" => $responseAbsences->getMessage(), "absences" => $Absences],
            $responseAbsences->getStatusCode(),
            [],
            ['groups' => 'absence']
        );
    }

    //! --------------------------------------------------------------------------------------------

    #[Route("newAbsence", name: "newAbsence", methods: ["POST"])]
    public function newAbsence()
    {

    }

    //! --------------------------------------------------------------------------------------------

    #[Route("updateAbsence", name: "updateAbsence", methods: ["PUT"])]
    public function updateAbsence()
    {

    }

    //! --------------------------------------------------------------------------------------------

    #[Route("deleteAbsence", name: "deleteAbsence", methods: ["DELETE"])]
    public function deleteAbsence()
    {

    }
}
