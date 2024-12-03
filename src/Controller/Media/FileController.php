<?php

namespace App\Controller\Media;

use App\Controller\BaseController;
use App\Service\Media\FileService;
use App\Service\Media\PictureService;
use App\Service\PhpseclibService;
use App\Service\User\ContactService;
use App\Service\User\UserService;
use App\Service\ValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;


#[Route('/api/file', name: "app_file")]
class FileController extends BaseController
{

    public function __construct(
        private PhpseclibService $phpseclibService,
        private ParameterBagInterface $params,
        private FileService $fileService,
        private ValidatorService $validatorService,
        private PictureService $pictureService,
        public UserService $userService,
        public ContactService $contactService,
        private EntityManagerInterface $em
    ) {
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Uploads a file to the server.
     *
     * This method handles the file upload process, including validation, storage, and response generation.
     *
     * @param Request $request The HTTP request containing the file to upload.
     * @param string $category The category of the file (recipe, menu, inventory).
     *
     * @return JsonResponse A JSON response indicating the status of the file upload.
     */
    #[Route('/{category}/upload', methods: ['POST'])]
    public function upload(Request $request, string $category): JsonResponse
    {
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }
        $isPrivate = $request->get('isPrivate', false);

        $fileResponse = $this->fileService->uploadFile($isPrivate, $uploadedFile, $category);
        if (!$fileResponse->isSuccess()) {
            return new JsonResponse(['error' => $fileResponse->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $filePath = $fileResponse->getData()[ 'filePath' ];

        return new JsonResponse([
            'message'  => 'File uploaded successfully',
            'filePath' => $filePath
        ]);
    }

    //! --------------------------------------------------------------------------------------------


    /**
     * Downloads a private file from the server.
     *
     * @param string $category The category of the file (recipe, menu, inventory).
     * @param int $id The ID of the file to download.
     *
     * @return Response A response containing the file to download.
     */
    #[Route('/downloadFile/{category}/{id}', name: 'downloadFile', methods: ['GET'])]
    public function downloadFile(string $category, int $id): Response
    {
        // Récupérer le chemin du fichier à partir de la base de données
        $filePath = $this->fileService->getOneFilePath($id, $category);
        if (!$filePath->isSuccess()) {
            return new Response($filePath->getMessage(), Response::HTTP_NOT_FOUND);
        }
        return $this->fileService->downloadFile($filePath->getData()[ 'filePath' ]);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Deletes a file from the server.
     *
     * @param string $filePath The path of the file to delete.
     *
     * @return JsonResponse A JSON response indicating the status of the file deletion.
     */
    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $filePath = $this->validatorService->validateJson($request);
        if (!$filePath) {
            return new JsonResponse(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }
        $filePath = $filePath->getData()[ 'filePath' ];
        $response = $this->fileService->deleteFile($filePath);
        if (!$response->isSuccess()) {
            return new JsonResponse(['error' => $response->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new JsonResponse([
            'message' => 'File: ' . $filePath . ' deleted successfully'
        ]);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Updates the avatar for a specified entity in the database and saves it to the server.
     *
     * @param Request $request The HTTP request containing the file data for the avatar update.
     * @param string $entityName The name of the entity to update (user or contact).
     * @param int $id The ID of the entity whose avatar is being updated.
     *
     * @return JsonResponse A JSON response indicating the status of the avatar update.
     */

    #[Route('/updateAvatar/{entityName}/{id}', name: 'updateAvatar', methods: 'post')]
    public function updateAvatar(Request $request, string $entityName, int $id): JsonResponse
    {
        try {
            $response = $this->pictureService->updateAvatar($request, $id, $entityName, $this, "picture");
            if (!$response->isSuccess()) {
                return $this->json([$response->getMessage(), $response->getData()], $response->getStatusCode());
            }
            return $this->json($response->getMessage(), Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->json($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Supprime l'avatar actuel d'une entité.
     *
     * Cette méthode permet de supprimer l'avatar associé à une entité spécifique (par exemple, un utilisateur ou un contact).
     * L'avatar est supprimé du serveur et de la base de données. Si l'entité ou l'avatar n'existe pas, une réponse d'erreur est retournée.
     *
     * @Route("/deleteAvatar/{entityName}/{id}", name="deleteAvatar", methods={"DELETE"})
     *
     * @param int $id L'identifiant de l'entité.
     * @param string $entityName Le nom de l'entité (exemple : "Contact", "User").
     *
     * @return JsonResponse Une réponse JSON contenant le message de succès ou d'erreur.
     *
     * @throws \InvalidArgumentException Si le nom de l'entité est invalide.
     * @throws \Exception En cas d'erreur lors de la suppression.
     */
    #[Route('/deleteAvatar/{entityName}/{id}', name: 'deleteAvatar', methods: 'delete')]
    public function deleteAvatar(int $id, string $entityName): JsonResponse
    {
        $repositoryName = "App\\Entity\\User\\" . ucfirst($entityName);
        $entity = $this->em->getRepository($repositoryName)->find($id);

        if (!$entity) {
            return $this->json(ucfirst($entityName) . ' non trouvé(e)', Response::HTTP_NOT_FOUND);
        }
        $currentAvatar = $entity->getAvatar();
        if (!$currentAvatar) {
            return $this->json('Avatar non trouvé', Response::HTTP_NOT_FOUND);
        }

        $response = $this->pictureService->deleteCurrentAvatar($currentAvatar, $entity);
        if (!$response->isSuccess()) {
            return $this->json($response->getMessage(), $response->getStatusCode());
        }
        $this->em->flush();
        return $this->json('Avatar supprimé avec succès', Response::HTTP_OK);
    }
}
