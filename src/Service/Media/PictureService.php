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
        private FileService $fileService,
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

    public function uploadPicture(Request $request, string $category): ApiResponse
    {
        try {
            $dataResponse = $this->getPictureData($request);
            if (!$dataResponse->isSuccess()) {
                return $dataResponse;
            }

            [$isPrivate, $uploadedFile, $originalFilename, $mime] = $dataResponse->getData();
            $uploadResponse = $this->fileService->uploadFile($isPrivate, $uploadedFile, $category);
            if (!$uploadResponse->isSuccess()) {
                return $uploadResponse;
            }
            $picturePath = $uploadResponse->getData()[ 'filePath' ];
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
     * Updates the avatar of a given entity.
     *
     * This method retrieves the entity by its ID, updates its avatar with the uploaded file,
     * and persists the changes to the database.
     *
     * @param Request $request The HTTP request containing the uploaded file.
     * @param int $Id The ID of the entity to update.
     * @param string $entityName The name of the entity to update (e.g., 'user', 'business').
     * @param object $thisClass The instance of the class calling this method.
     * @param string $category The category of the file (recipe, menu, inventory).
     *
     * @return ApiResponse An API response indicating success or failure with appropriate data and status code.
     *
     * @throws Exception If an error occurs during the update of the avatar.
     */
    public function updateAvatar(Request $request, int $Id, string $entityName, object $thisClass, string $category): ApiResponse
    {
        try {
            $methodName = 'find' . ucfirst($entityName);
            $serviceName = "{$entityName}Service";
            $entityResponse = $thisClass->$serviceName->$methodName($Id);
            if (!$entityResponse->isSuccess()) {
                return $entityResponse;
            }
            $entity = $entityResponse->getData()[$entityName];

            $deleteResponse = $this->deleteCurrentAvatar($entity);
            if (!$deleteResponse->isSuccess()) {
                return $deleteResponse;
            }

            $uploadResponse = $this->uploadPicture($request, category: $category);
            if (!$uploadResponse->isSuccess()) {
                return $uploadResponse;
            }
            $avatar = $uploadResponse->getData()[ 'picture' ];
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
        // le fichier est il privé
        $isPrivate = $request->get('isPrivate', false);

        // Obtenir le nom original et le MIME
        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $mimeType = $uploadedFile->guessExtension();
        $mime = $this->em->getRepository(Mime::class)->findOneBy(["name" => $mimeType]);

        $allowedMimeTypes = $this->em->getRepository(Mime::class)->findAll();
        $allowedMimeTypes = array_map(fn($mime): string => $mime->getName(), $allowedMimeTypes);

        if ($allowedMimeTypes && !in_array($mimeType, $allowedMimeTypes)) {
            return ApiResponse::error("Invalid file type: $mimeType", null, Response::HTTP_BAD_REQUEST);
        }

        return ApiResponse::success("Picture data retrieved successfully", [$isPrivate, $uploadedFile, $originalFilename, $mime], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Supprime l'avatar associé à une entité donnée de la base de données et du serveur.
     *
     * @param object $entity L'entité dont l'avatar doit être supprimé.
     * @return ApiResponse Réponse indiquant le succès ou l'échec de l'opération.
     */
    private function deleteCurrentAvatar(object $entity): ApiResponse
    {
        // supprimer l'avatar actuel du serveur 
        $currentAvatar = $entity->getAvatar();
        $deleteFileResponse = $this->fileService->deleteFile($currentAvatar->getPath());
        if (!$deleteFileResponse->isSuccess()) {
            return $deleteFileResponse;
        }

        // supprimer l'avatar actuel de la bdd
        if ($this->em->remove($currentAvatar)) {
            $this->em->flush();
            return ApiResponse::success("Current Avatar removed successfully", [], Response::HTTP_OK);
        } else {
            return ApiResponse::error("Error while removing current Avatar: ", null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! --------------------------------------------------------------------------------------------


}