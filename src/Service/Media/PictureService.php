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

    public function createPicture(Request $request): ApiResponse
    {
        try {
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

            // Ajout d'un paramètre pour définir si le fichier est privé ou public
            $isPrivate = $request->request->get('is_private', false); // Par défaut, c'est public

            // Définir le chemin de stockage en fonction de l'état privé/public
            $serverPath = $isPrivate ? $this->params->get('server_private_files_path') : $this->params->get('server_files_path');

            // Définir le chemin complet où stocker le fichier sur le serveur distant
            $destination = "{$serverPath}/user/{ $originalFilename}";

            // Générer un slug basé sur le nom original
            $slug = $this->slugify($originalFilename);

            // Créer l'entité Picture
            $picture = (new Picture())
                ->setMime($mime)
                ->setSlug($slug)
                ->setName($originalFilename)
                ->setPath($destination);
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

    /**
     * Generates a URL-friendly slug from an original filename.
     *
     * This method cleans the filename by:
     * - Removing common dimension patterns (e.g., "1024x640").
     * - Replacing non-alphanumeric characters with hyphens.
     * - Trimming excess hyphens from the start or end of the string.
     * - Converting the result to lowercase using a slugger utility.
     *
     * @param string $originalFilename The original filename (without path).
     * 
     * @return string The cleaned and slugified filename.
     */
    private function slugify(string $originalFilename): string
    {
        // Remove dimension patterns at the end of the filename (e.g., "1024x640")
        $cleanedFilename = preg_replace('/\d+x\d+$/', '', $originalFilename);

        // Replace non-alphanumeric characters with hyphens
        $cleanedFilename = preg_replace('/[^a-zA-Z0-9]+/', '-', $cleanedFilename);

        // Trim hyphens from the start and end
        $cleanedFilename = trim($cleanedFilename, '-');

        // Convert the cleaned filename to a slug using the slugger and return it
        return $this->slugger->slug($cleanedFilename)->lower();
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
    public function updateAvatar(Request $request, int $Id, string $entityName, object $service): ApiResponse
    {
        try {
            $methodName = 'find' . ucfirst($entityName);
            $serviceName = "{$entityName}Service";
            $entity = $service->$serviceName->$methodName($Id);
            if (!$entity->isSuccess()) {
                return $entity;
            }
            $entity = $entity->getData()[$entityName];
            $avatar = $this->createPicture($request);
            if (!$avatar->isSuccess()) {
                return $avatar;
            }
            $avatar = $avatar->getData()[ 'picture' ];
            // Associer l'avatar à l'utilisateur
            $entity->setAvatar($avatar);

            $this->em->flush();
            return ApiResponse::success("Avatar updated successfully", [], Response::HTTP_OK);
        } catch (Exception $exception) {
            return ApiResponse::error("error while updating Avatar :" . $exception->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    //! --------------------------------------------------------------------------------------------

}