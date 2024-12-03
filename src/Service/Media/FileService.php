<?php

namespace App\Service\Media;

use App\Service\PhpseclibService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use App\Utils\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;


class FileService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JsonResponseBuilder $jsonResponseBuilder,
        private ValidatorService $validateService,
        private SluggerInterface $slugger,
        private ParameterBagInterface $params,
        private PhpseclibService $phpseclibService

    ) {
    }

    //! --------------------------------------------------------------------------------------------


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
    public function slugify(string $originalFilename): string
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
     * Retrieves a file path by its ID and category.
     *
     * This method fetches a file path from the database using the provided ID and category. 
     * If no file is found with the given ID and category, it returns an error response. 
     * Otherwise, it returns a success response with the file path data.
     *
     * @param int $id The ID of the file to retrieve.
     * @param string $category The category of the file (recipe, menu, inventory).
     *
     * @return ApiResponse Returns an error response if no file is found,
     *                     or a success response with the file path data if found.
     */
    public function getOneFilePath(int $id, string $category): ApiResponse
    {

        $category = ucfirst($category);
        $entity = $this->em->getRepository("App\Entity\Media\\$category")->find($id);

        // Vérifier si l'entité existe
        if (!$entity) {
            return ApiResponse::error(ucfirst($category) . ' non trouvé(e)', null, Response::HTTP_NOT_FOUND);
        }

        // Récupérer le chemin du fichier dans la base de données
        $filePath = $entity->getPath();
        if (!$filePath) {
            return ApiResponse::error('Fichier non trouvé', null, Response::HTTP_NOT_FOUND);
        }

        // Vérifier si le fichier existe sur le serveur distant
        if (!$this->phpseclibService->fileExists($filePath)) {
            return ApiResponse::error('Fichier introuvable sur le serveur distant: ' . $filePath, null, Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::success("filePath retrieved succesfully", ["filePath" =>$filePath], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Handles file upload for a specific category, with support for private or public storage.
     *
     * @param Request $request The HTTP request containing the file and additional parameters.
     * @param string $category The category under which the file should be stored (e.g., recipe, menu).
     * @param bool $isPrivate Indicates whether the file should be stored in a private directory (default: false).
     * @return ApiResponse Returns an ApiResponse indicating success or failure of the file upload.
     */
    public function uploadFile(Request $request, $category, $isPrivate = false): ApiResponse
    {
        // Récupérer le fichier téléversé depuis la requête
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return ApiResponse::error('Fichier non trouvé', null, Response::HTTP_BAD_REQUEST);
        }

        // Vérifie si la catégorie est valide
        $validCategories = ['recipe', 'menu', 'inventory', "picture"];
        if (!in_array($category, $validCategories)) {
            return ApiResponse::error('Catégorie non gérée', null, Response::HTTP_BAD_REQUEST);
        }

        $filePath = $this->createFilePath($request, $uploadedFile, $category);

        // Téléverser le fichier
        try {
            $this->phpseclibService->uploadFile($uploadedFile->getPathname(), $filePath);
        } catch (\Exception $e) {
            return ApiResponse::error('Erreur lors du téléversement du fichier', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return ApiResponse::success('Fichier téléversé avec succès', ['filePath' => $filePath], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Generates a full file path for uploaded files based on category and privacy setting.
     *
     * This method determines whether the file should be stored in a private or public directory
     * and appends the category and a unique identifier to the file name to ensure uniqueness.
     *
     * @param Request $request The HTTP request containing an optional 'is_private' parameter.
     * @param $uploadedFile The file being uploaded, with access to its original name.
     * @param string $category The category under which the file should be stored (e.g., 'recipe', 'menu').
     *
     * @return string The complete file path where the file will be stored on the server.
     *
     * @throws \InvalidArgumentException If the $category is invalid or the $uploadedFile is not provided.
     */
    public function createFilePath(Request $request, $uploadedFile, $category)
    {
        // Ajout d'un paramètre pour définir si le fichier est privé ou public
        $isPrivate = $request->request->get('is_private', false);

        // Définir le chemin de stockage en fonction de l'état privé/public
        $serverPath = $isPrivate ? $this->params->get('server_private_files_path') : $this->params->get('server_files_path');

        // Définir le chemin complet où stocker le fichier sur le serveur distant
        $fileName = $uploadedFile->getClientOriginalName();
        $filePath = $serverPath . "/" . $category . "/" . $fileName;

        return $filePath;
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Downloads a file from the server.
     *
     * This method uses the Phpseclib service to download a file from a remote server
     * based on the provided file path. 
     * If the file is successfully downloaded.
     * @return BinaryFileResponse
     * @throws \Exception if the download fails.
     * */
    public function downloadFile($filePath): BinaryFileResponse
    {
        return $this->phpseclibService->downloadFile($filePath);
    }

    //! --------------------------------------------------------------------------------------------

}