<?php

namespace App\Service\Media;

use App\Entity\Media\Mime;
use App\Entity\Media\Picture;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;


class PictureService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JsonResponseBuilder $jsonResponseBuilder,
        private ValidatorService $validateService,
        private SluggerInterface $slugger,
        private ParameterBagInterface $params,
        private FileService $fileService
    ) {
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Handles the creation of a new picture from an uploaded file.
     *
     * This method processes an uploaded file, validates its type, generates a slug,
     * determines its storage path (public or private), and persists the picture entity.
     *
     * @param Request $request The HTTP request containing the uploaded file and optional privacy flag.
     *
     * @return ApiResponse An API response indicating success or failure with appropriate data and status code.
     *
     * @throws Exception If an error occurs during the creation of the picture entity.
     */

    public function createPicture(Request $request, string $category): ApiResponse
    {
        try {
            $dataResponse = $this->getPictureData($request);
            if (!$dataResponse->isSuccess()) {
                return $dataResponse;
            }

            [$uploadedFile, $originalFilename, $mime] = $dataResponse->getData();
            $picturePath = $this->fileService->createFilePath($request, $uploadedFile, $category);
            $slug = $this->fileService->slugify($originalFilename);

            $picture = (new Picture())
                ->setMime($mime)
                ->setSlug($slug)
                ->setName($originalFilename)
                ->setPath($picturePath);
            $this->em->persist($picture);

            $response = $this->validateService->validateEntity($picture);
            if (!$response->isSuccess()) {
                return $response;
            }

            return ApiResponse::success("Picture created successfully", ["picture" => $picture], Response::HTTP_OK);
        } catch (Exception $exception) {
            return ApiResponse::error("Error while updating avatar: " . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    //! --------------------------------------------------------------------------------------------

    /**
     * Updates the avatar of a specified entity.
     *
     * @param Request $request The HTTP request containing the file upload.
     * @param int $Id The unique identifier of the entity to update.
     * @param string $entityName The name of the entity type (e.g., 'User', 'Contact', etc.).
     * @param object $service The service handling the operations for the specified entity type.
     *
     * @return ApiResponse
     * 
     * - Returns a success response if the avatar is updated successfully.
     * - Returns an error response if:
     *   - The entity is not found.
     *   - The uploaded picture is invalid.
     *   - Any exception occurs during processing.
     *
     * @throws Exception If an error occurs while updating the avatar.
     */
    public function updateAvatar(Request $request, int $Id, string $entityName, object $service, string $category): ApiResponse
    {
        try {
            $methodName = 'find' . ucfirst($entityName);
            $serviceName = "{$entityName}Service";
            $entity = $service->$serviceName->$methodName($Id);
            if (!$entity->isSuccess()) {
                return $entity;
            }
            $entity = $entity->getData()[$entityName];
            $avatar = $this->createPicture($request, category: $category);
            if (!$avatar->isSuccess()) {
                return $avatar;
            }
            $avatar = $avatar->getData()[ 'picture' ];
            $entity->setAvatar($avatar);

            $this->em->flush();
            return ApiResponse::success("Avatar updated successfully", [], Response::HTTP_OK);
        } catch (Exception $exception) {
            return ApiResponse::error("error while updating Avatar :" . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Retrieves picture data from the uploaded file.
     *
     * This method validates the uploaded file, checks its MIME type, and ensures it is allowed.
     * If valid, it returns the original filename, MIME type, and the MIME entity from the database.
     *
     * @param Request $request The HTTP request containing the uploaded file.
     *
     * @return ApiResponse The API response containing the file data or an error message.
     *
     * @throws \Exception If an error occurs while processing the file.
     */
    public function getPictureData(Request $request): ApiResponse
    {
        // Récupérer le fichier téléchargé
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return ApiResponse::error("No file uploaded", null, Response::HTTP_BAD_REQUEST);
        }

        // Obtenir le nom original et le MIME
        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $mimeType = $uploadedFile->guessExtension();
        $mime = $this->em->getRepository(Mime::class)->findOneBy(["name" => $mimeType]);

        $allowedMimeTypes = $this->em->getRepository(Mime::class)->findAll();
        $allowedMimeTypes = array_map(fn($mime): string => $mime->getName(), $allowedMimeTypes);

        if ($allowedMimeTypes && !in_array($mimeType, $allowedMimeTypes)) {
            return ApiResponse::error("Invalid file type: $mimeType", null, Response::HTTP_BAD_REQUEST);
        }

        return ApiResponse::success("Picture data retrieved successfully", [$uploadedFile, $originalFilename, $mime], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

    public function deletePicture()
    {

    }

    //! --------------------------------------------------------------------------------------------

}