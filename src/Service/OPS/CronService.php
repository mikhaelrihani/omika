<?php

namespace App\Service\OPS;

use App\Service\Event\EventService;
use App\Utils\ApiResponse;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class CronService
{
    private $productsWithoutShelfCount;
    public function __construct(
        protected ProductService $productService,
        protected EventService $eventService,
        protected ProductEventService $productEventService
    ) {
        $this->productsWithoutShelfCount = 0;
    }
    //! --------------------------------------------------------------------------------------------

    public function load(): ApiResponse
    {
        $steps = [
            "productsWithoutShelf" => fn() => $this->createEventIfProductsWithoutShelf(),
        ];

        // Execute each step and handle exceptions
        foreach ($steps as $stepName => $step) {
            try {
                $step();
            } catch (Exception $e) {
                return ApiResponse::error(
                    "Step -{$stepName}- failed :" . $e->getMessage(),
                    null,
                    Response::HTTP_INTERNAL_SERVER_ERROR
                );
            }
        }

        return ApiResponse::success(
            "Found {$this->productsWithoutShelfCount} products without shelf",
            null,
            Response::HTTP_OK
        );
    }

    //! --------------------------------------------------------------------------------------------

    private function createEventIfProductsWithoutShelf(): void
    {
        $productsWithoutShelf = $this->productService->getProductsWithoutShelf();
        $this->productsWithoutShelfCount = count($productsWithoutShelf->getData());
        if ($this->productsWithoutShelfCount > 0) {
            $this->productEventService->createEventProductsWithoutShelf($this->productsWithoutShelfCount);
        }
    }

    //! --------------------------------------------------------------------------------------------

}