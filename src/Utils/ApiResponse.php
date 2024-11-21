<?php

namespace App\Utils;

class ApiResponse
{

    private function __construct(private bool $success, private string $message, private ?array $data = null, private ?string $errorCode = null)
    {

    }

    // Getters
    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    // Méthodes statiques pour créer une réponse avec succès ou erreur
    public static function success(string $message, ?array $data = null): self
    {
        return new self(true, $message, $data);
    }

    public static function error(string $message, ?array $data = null, ?string $errorCode = null): self
    {
        return new self(false, $message, $data, $errorCode);
    }
}