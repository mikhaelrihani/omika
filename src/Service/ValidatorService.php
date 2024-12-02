<?php

namespace App\Service;

use App\Utils\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service to validate entities and JSON payloads using Symfony Validator.
 */
class ValidatorService
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /**
     * Validates an entity against its constraints.
     *
     * @param object $entity The entity to validate.
     *
     * @return ApiResponse Validation result encapsulated in an ApiResponse object.
     */
    public function validateEntity(object $entity): ApiResponse
    {
        $errors = $this->validator->validate($entity);
        if (count($errors) > 0) {
            $errorMessages = $this->formatErrors($errors);
            return ApiResponse::error(
                message: "Validation failed",
                data: ['errors' => $errorMessages],
                statusCode: Response::HTTP_BAD_REQUEST
            );
        }

        return ApiResponse::success("Validation successful", [], Response::HTTP_OK);
    }

    //! --------------------------------------------------------------------------------------------

    private function formatErrors(ConstraintViolationListInterface $errors): array
    {
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = [
                'field'   => $error->getPropertyPath(),
                'message' => $error->getMessage()
            ];
        }

        return $errorMessages;
    }

    //! --------------------------------------------------------------------------------------------

    /**
     * Validates JSON payloads against custom constraints.
     *
     * @param Request $request The HTTP request containing JSON data.
     * @param Assert\Collection $constraints The constraints to validate the JSON data against.
     *
     * @return ApiResponse Validation result encapsulated in an ApiResponse object.
     */
    public function validateJson(Request $request, Assert\Collection $constraints = null): ApiResponse
    {
        // Decode JSON content
        $jsonContent = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error(
                "Invalid JSON payload",
                ['error' => json_last_error_msg()],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validate decoded data against the constraints
        $errors = $this->validator->validate($jsonContent, $constraints);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = [
                    'field'   => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }

            return ApiResponse::error(
                "Validation failed",
                ['errors' => $errorMessages],
                Response::HTTP_BAD_REQUEST
            );
        }

        return ApiResponse::success("JSON content is valid", $jsonContent, Response::HTTP_OK);
    }
}
