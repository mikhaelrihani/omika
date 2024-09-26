<?php

namespace App\Service;

use phpseclib3\Net\SFTP;
use Symfony\Component\HttpFoundation\BinaryFileResponse;


class PhpseclibService
{

    protected $sftp;


    public function __construct(string $host, string $username, string $password)
    {
        $this->sftp = new SFTP($host);
        if (!$this->sftp->login($username, $password)) {
            throw new \Exception('Login failed');
        }

    }

    public function uploadFile($localFile, $remoteFile)
    {
        if (!$this->sftp->put($remoteFile, $localFile, SFTP::SOURCE_LOCAL_FILE)) {
            throw new \Exception("Upload failed");
        }
    }
    public function downloadFile($remoteFilePath): BinaryFileResponse
    {
        // Create a temporary file path
        $tempFilePath = tempnam(sys_get_temp_dir(), 'file_');
    
        // Tenter de télécharger le fichier depuis le serveur SFTP
        if (!$this->sftp->get($remoteFilePath, $tempFilePath)) {
            throw new \Exception("Download failed");
        }
    
        // Créer une réponse binaire
        $response = new BinaryFileResponse($tempFilePath);
    
        // Déterminer le type de contenu en fonction de l'extension du fichier
        $fileExtension = pathinfo($remoteFilePath, PATHINFO_EXTENSION);
        switch (strtolower($fileExtension)) {
            case 'pdf':
                $contentType = 'application/pdf';
                break;
            case 'xls':
            case 'xlsx':
                $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'csv':
                $contentType = 'text/csv';
                break;
            case 'txt':
                $contentType = 'text/plain';
                break;
            case 'jpg':
            case 'jpeg':
                $contentType = 'image/jpeg';
                break;
            case 'png':
                $contentType = 'image/png';
                break;
            default:
                $contentType = 'application/octet-stream'; // Fallback pour les types non gérés
                break;
        }
    
        // Définir le type de contenu et les en-têtes de disposition
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($remoteFilePath) . '"');
        $response->headers->set('Content-Length', filesize($tempFilePath)); // Ajouter la taille du contenu
    
        // Supprimer le fichier temporaire après utilisation
        register_shutdown_function(function () use ($tempFilePath) {
            @unlink($tempFilePath); // Supprimer le fichier temporaire
        });
    
        return $response; // Retourner la réponse
    }
    
    
    

    public function listFiles($remoteDirectory): array
    {
        return $this->sftp->nlist($remoteDirectory);
    }

    public function deleteFile($filePath)
    {
        if (!$this->sftp->delete($filePath)) {
         
            $error = $this->sftp->getLastError();
            throw new \Exception("Delete failed for path: $filePath. Error: $error");
        }
    }

    public function fileExists($remoteFilePath): bool
    {
        return $this->sftp->file_exists($remoteFilePath);
    }




}