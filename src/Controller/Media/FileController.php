<?php

namespace App\Controller\Media;

use App\Controller\BaseController;
use App\Repository\carte\MenuRepository;
use App\Repository\inventory\InventoryRepository;
use App\Repository\recipe\RecipeRepository;
use App\Service\PhpseclibService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/api/media/file', name: "app_file")]
class FileController extends BaseController
{
    private PhpseclibService $phpseclibService;
    private ParameterBagInterface $params;
    private RecipeRepository $recipeRepository;
    private MenuRepository $menuRepository;
    private InventoryRepository $inventoryRepository;


    function __construct(PhpseclibService $phpseclibService, ParameterBagInterface $params, RecipeRepository $recipeRepository, MenuRepository $menuRepository, InventoryRepository $inventoryRepository)
    {
        $this->phpseclibService = $phpseclibService;
        $this->params = $params;
        $this->recipeRepository = $recipeRepository;
        $this->menuRepository = $menuRepository;
        $this->inventoryRepository = $inventoryRepository;
    }


    //! UPLOAD FILE to server
    #[Route('/{category}/upload', methods: ['POST'])]
    public function upload(Request $request, string $category): JsonResponse
    {
        // Récupérer le fichier téléversé depuis la requête
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'File not found'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifie si la catégorie est valide
        $validCategories = ['recipe', 'menu', 'inventory'];
        if (!in_array($category, $validCategories)) {
            return new JsonResponse(['error' => 'Catégorie invalide'], Response::HTTP_BAD_REQUEST);
        }

        // Ajout d'un paramètre pour définir si le fichier est privé ou public
        $isPrivate = $request->request->get('is_private', false); // Par défaut, c'est public

        // Définir le chemin de stockage en fonction de l'état privé/public
        if ($isPrivate) {
            $serverPath = $this->params->get('server_private_files_path');
        } else {
            $serverPath = $this->params->get('server_files_path');
        }

        // Définir le chemin complet où stocker le fichier sur le serveur distant
        $fileName = $uploadedFile->getClientOriginalName();
        $filePath = $serverPath . "/" . $category . "/" . $fileName;

        // Téléverser le fichier
        try {
            $this->phpseclibService->uploadFile($uploadedFile->getPathname(), $filePath);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Retourner une réponse JSON avec le chemin du fichier téléversé
        return new JsonResponse([
            'message'  => 'File uploaded successfully',
            'filePath' => $filePath
        ]);
    }

    //! GET private file

    #[Route('/private/{category}/{id}', name: 'serve_private', methods: ['GET'])]
    public function servePrivateFile(string $category, int $id): Response
    {
        // Vérification si la catégorie est valide
        $validCategories = ['recipe', 'menu', 'inventory'];
        if (!in_array($category, $validCategories)) {
            return new JsonResponse(['error' => 'Catégorie invalide'], Response::HTTP_BAD_REQUEST);
        }
    
        // Sélection du bon repository en fonction de la catégorie
        switch ($category) {
            case 'recipe':
                $entity = $this->recipeRepository->find($id);
                break;
            case 'menu':
                $entity = $this->menuRepository->find($id);
                break;
            case 'inventory':
                $entity = $this->inventoryRepository->find($id);
                break;
            default:
                return new JsonResponse(['error' => 'Catégorie non gérée'], Response::HTTP_BAD_REQUEST);
        }
    
        // Vérifier si l'entité existe
        if (!$entity) {
            return new JsonResponse(['error' => ucfirst($category) . ' non trouvé(e)'], Response::HTTP_NOT_FOUND);
        }
    
        // Récupérer le chemin du fichier dans la base de données
        $filePath = $entity->getPath();
        if (!$filePath) {
            return new JsonResponse(['error' => 'Fichier non trouvé'], Response::HTTP_NOT_FOUND);
        }
    
        // Chemin complet vers le fichier sur le serveur distant
        $serverPrivateFilesPath = $this->params->get('server_private_files_path');
        $fullFilePath = $serverPrivateFilesPath . '/' . $category . '/' . $filePath;
    
        // Vérifier si le fichier existe sur le serveur distant
        if (!$this->phpseclibService->fileExists($fullFilePath)) {
            return new JsonResponse(['error' => 'Fichier introuvable sur le serveur distant: ' . $fullFilePath], Response::HTTP_NOT_FOUND);
        }
    
        // Télécharger le fichier temporairement pour le renvoyer à l'utilisateur
        $tempFilePath = tempnam(sys_get_temp_dir(), 'file_');
        $this->phpseclibService->downloadFile($fullFilePath, $tempFilePath);
    
        // Créer la réponse pour le téléchargement
        $response = new BinaryFileResponse($tempFilePath);
        
        // Set content disposition to download the file
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($fullFilePath) // Provide the filename to the user
        );
    
        // Use register_shutdown_function to clean up the temp file after the response is sent
        register_shutdown_function(function() use ($tempFilePath) {
            @unlink($tempFilePath); // Delete the temporary file
        });
    
        return $response; // Return the response for download
    }
    

    //! Delete
    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request): JsonResponse
    {
        // Récupère le JSON
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

}
