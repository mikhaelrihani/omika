<?php

namespace App\Serializer;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Class EntityDenormalizer
 * 
 * Custom denormalizer that integrates with Doctrine's EntityManager to deserialize 
 * entities by their identifier. It checks if an entity exists in the database based on 
 * its ID and returns the entity instead of creating a new one, allowing entity linking during 
 * the deserialization process.
 */
class EntityDenormalizer implements DenormalizerInterface
{
    /**
     * @var EntityManagerInterface
     * 
     * The Doctrine EntityManager used to fetch entities from the database.
     */
    private $entityManager;

    /**
     * EntityDenormalizer constructor.
     * 
     * @param EntityManagerInterface $entityManager
     * 
     * Injects the EntityManager which will be used to retrieve entities.
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Denormalizes data into an entity.
     * 
     * This method is called during the deserialization process when Symfony detects that 
     * the data being deserialized is an ID and the target type is an entity.
     * 
     * It attempts to retrieve the entity from the database based on its ID.
     * 
     * @param mixed $data The data to denormalize (typically an entity ID).
     * @param string $type The class of the object that should be instantiated.
     * @param string|null $format The format being deserialized from (e.g., 'json').
     * @param array $context Options that normalizers have access to.
     * 
     * @return mixed The denormalized entity if found in the database, otherwise null.
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): mixed
    {
        // Use the EntityManager to find the entity by its type and ID
        return $this->entityManager->find($type, $data);
    }

    /**
     * Checks if this denormalizer supports the given data and type.
     * 
     * This method is used to determine if this denormalizer should handle the current deserialization request.
     * It checks whether the data is an integer (i.e., an ID) and if the type is an entity from the App\Entity namespace.
     * 
     * @param mixed $data The data that should be denormalized.
     * @param string $type The class of the object that should be instantiated.
     * @param string|null $format The format being deserialized from.
     * @param array $context Options available during the deserialization process.
     * 
     * @return bool Returns true if the denormalizer supports the given data and type, false otherwise.
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // Check if data is an integer (presumed entity ID) and the type starts with "App\Entity"
        return is_int($data) && strpos($type, "App\Entity") === 0;
    }

    /**
     * Returns the types supported by this denormalizer.
     * 
     * This method is optional but can be used to optimize the denormalization process by
     * specifying which types this denormalizer supports. In this case, it supports any class
     * in the "App\Entity" namespace.
     * 
     * @param string|null $format The format being denormalized from.
     * 
     * @return array An array mapping supported types to a boolean value.
     */
    public function getSupportedTypes(?string $format): array
    {
        // Specify that this denormalizer supports any class within the App\Entity namespace
        return [
            'App\Entity\*' => true,
        ];
    }
}
