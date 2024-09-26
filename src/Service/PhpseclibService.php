<?php

namespace App\Service;

use phpseclib3\Net\SFTP;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Class PhpseclibService
 *
 * A service for handling SFTP file operations, including upload, download, 
 * listing, and deletion of files on a remote server using phpseclib.
 */
class PhpseclibService
{
    /**
     * @var SFTP
     */
    protected $sftp;

    /**
     * PhpseclibService constructor.
     *
     * Initializes the SFTP connection with the provided host, username, and password.
     *
     * @param string $host The SFTP server host.
     * @param string $username The username for authentication.
     * @param string $password The password for authentication.
     * 
     * @throws \Exception If the login fails.
     */
    public function __construct(string $host, string $username, string $password)
    {
        $this->sftp = new SFTP($host);
        if (!$this->sftp->login($username, $password)) {
            throw new \Exception('Login failed');
        }
    }

    /**
     * Upload a file to the remote SFTP server.
     *
     * @param string $localFile The path to the local file to upload.
     * @param string $remoteFile The path on the remote server to store the uploaded file.
     * 
     * @throws \Exception If the upload fails.
     */
    public function uploadFile($localFile, $remoteFile)
    {
        if (!$this->sftp->put($remoteFile, $localFile, SFTP::SOURCE_LOCAL_FILE)) {
            throw new \Exception("Upload failed");
        }
    }

    /**
     * Download a file from the remote SFTP server.
     *
     * Creates a temporary file to store the downloaded content and prepares a 
     * BinaryFileResponse to send it to the client.
     *
     * @param string $remoteFilePath The path to the file on the remote server.
     * 
     * @return BinaryFileResponse The response containing the downloaded file.
     * 
     * @throws \Exception If the download fails.
     */
    public function downloadFile($remoteFilePath): BinaryFileResponse
    {
        // Create a temporary file path
        $tempFilePath = tempnam(sys_get_temp_dir(), 'file_');

        // Attempt to download the file from the SFTP server
        if (!$this->sftp->get($remoteFilePath, $tempFilePath)) {
            throw new \Exception("Download failed");
        }

        // Create a binary response
        $response = new BinaryFileResponse($tempFilePath);

        // Determine the content type based on the file extension
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
                $contentType = 'application/octet-stream'; // Fallback for unhandled types
                break;
        }

        // Set the content type and disposition headers
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($remoteFilePath) . '"');
        $response->headers->set('Content-Length', filesize($tempFilePath)); // Add content length

        // Delete the temporary file after use
        register_shutdown_function(function () use ($tempFilePath) {
            @unlink($tempFilePath); // Remove the temporary file
        });

        return $response; // Return the response
    }

    /**
     * List files in a specified remote directory.
     *
     * @param string $remoteDirectory The path to the remote directory.
     * 
     * @return array An array of file names in the specified directory.
     */
    public function listFiles($remoteDirectory): array
    {
        return $this->sftp->nlist($remoteDirectory);
    }

    /**
     * Delete a file from the remote SFTP server.
     *
     * @param string $filePath The path to the file on the remote server.
     * 
     * @throws \Exception If the deletion fails.
     */
    public function deleteFile($filePath)
    {
        if (!$this->sftp->delete($filePath)) {
            $error = $this->sftp->getLastError();
            throw new \Exception("Delete failed for path: $filePath. Error: $error");
        }
    }

    /**
     * Check if a file exists on the remote SFTP server.
     *
     * @param string $remoteFilePath The path to the file on the remote server.
     * 
     * @return bool True if the file exists, false otherwise.
     */
    public function fileExists($remoteFilePath): bool
    {
        return $this->sftp->file_exists($remoteFilePath);
    }
}
