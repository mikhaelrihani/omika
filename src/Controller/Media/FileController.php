<?php

namespace App\Controller\Media;

use App\Controller\BaseController;
use App\Service\Media\FileService;
use App\Service\PhpseclibService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Class FileController
 *
 * Handles file upload, retrieval, and deletion operations for various media categories 
 * (recipe, menu, inventory) through an API.
 */
#[Route('/api/file', name: "app_file")]
class FileController extends BaseController
{

    public function __construct(
        private PhpseclibService $phpseclibService,
        private ParameterBagInterface $params,
        private FileService $fileService
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
        $fileResponse = $this->fileService->uploadFile($request, $category);
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
    #[Route('/downloadPrivateFile/{category}/{id}', name: 'downloadPrivateFile', methods: ['GET'])]
    public function downloadPrivateFile(string $category, int $id): Response
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
     * @param Request $request The HTTP request containing the file path to delete.
     *
     * @return JsonResponse A JSON response with the status of the deletion.
     */
    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $filePath = $data[ 'filePath' ] ?? null;

        if (!$filePath) {
            return new JsonResponse(['error' => 'filePath not found'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->phpseclibService->deleteFile($filePath);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'message' => 'File ' . $filePath . ' deleted successfully'
        ]);
    }

    //! --------------------------------------------------------------------------------------------

}
