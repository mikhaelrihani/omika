<?php

namespace App\Utils;

/**
 * Classe utilitaire pour créer des réponses structurées pour une API.
 */
class ApiResponse
{
    /**
     * @var bool $success Indique si la réponse est un succès.
     */
    private bool $success;

    /**
     * @var string $message Message associé à la réponse.
     */
    private string $message;

    /**
     * @var array|null $data Données supplémentaires incluses dans la réponse.
     */
    private ?array $data;

    /**
     * @var int|null $statusCode Code de statut HTTP associé à la réponse.
     */
    private ?int $statusCode;

    /**
     * Constructeur privé pour garantir l'utilisation des méthodes statiques.
     *
     * @param bool $success Indique si la réponse est un succès.
     * @param string $message Message associé à la réponse.
     * @param array|null $data Données supplémentaires incluses dans la réponse.
     * @param int|null $statusCode Code de statut HTTP associé à la réponse.
     */
    private function __construct(
        bool $success,
        string $message,
        ?array $data = null,
        ?int $statusCode = null
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->statusCode = $statusCode;
    }

    /**
     * Vérifie si la réponse est un succès.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Récupère le message associé à la réponse.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Récupère les données incluses dans la réponse.
     *
     * @return array|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * Récupère le code de statut HTTP associé à la réponse.
     *
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Crée une réponse de succès.
     *
     * @param string $message Message de succès.
     * @param array|null $data Données supplémentaires.
     * @param int|null $statusCode Code de statut HTTP (par défaut, null).
     * @return self
     */
    public static function success(string $message, ?array $data = null, ?int $statusCode = null): self
    {
        return new self(true, $message, $data, $statusCode);
    }

    /**
     * Crée une réponse d'erreur.
     *
     * @param string $message Message d'erreur.
     * @param array|null $data Données supplémentaires pour contextualiser l'erreur.
     * @param int|null $statusCode Code de statut HTTP (par défaut, null).
     * @return self
     */
    public static function error(string $message, ?array $data = null, ?int $statusCode = null): self
    {
        return new self(false, $message, $data, $statusCode);
    }
}
