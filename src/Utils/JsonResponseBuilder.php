<?php

namespace App\Utils;

use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Classe utilitaire pour construire des réponses JSON avec prise en charge de la sérialisation.
 */
class JsonResponseBuilder
{
    /**
     * @var SerializerInterface Le sérialiseur Symfony utilisé pour transformer les données en JSON.
     */
    private SerializerInterface $serializer;

    /**
     * Constructeur.
     *
     * @param SerializerInterface $serializer Le service de sérialisation Symfony.
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Crée une réponse JSON avec des données sérialisées.
     *
     * @param mixed $data Les données à inclure dans la réponse. 
     *                    Peut être un tableau, un objet ou une valeur primitive.
     * @param int $status Le code de statut HTTP de la réponse. Par défaut, 200.
     * @param array $groups Un tableau de groupes de sérialisation pour filtrer les propriétés sérialisées. Par défaut, vide.
     *
     * @return JsonResponse Une réponse JSON contenant les données sérialisées.
     *
     * Exemple d'utilisation :
     * ```php
     * $response = $jsonResponseBuilder->createJsonResponse($data, 200, ['groupName']);
     * ```
     */
    public function createJsonResponse(mixed $data, int $status = 200, array $groups = []): JsonResponse
    {
        $context = $groups ? ['groups' => $groups] : [];
        $jsonData = $this->serializer->serialize($data, 'json', $context);

        return new JsonResponse($jsonData, $status, [], true);
    }
}
