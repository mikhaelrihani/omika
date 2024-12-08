<?php
namespace App\Service\OPS;

use App\Entity\Product\Product;
use App\Entity\Supplier\Supplier;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Event\EventService;
use App\Utils\ApiResponse;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;


class ProductEventService
{
    private DateTimeImmutable $now;
    function __construct(
        private EntityManagerInterface $em,
        protected EventService $eventService,
    ) {
        $this->now = new DateTimeImmutable('today');
    }


    //! ----------------------------------------------------------------------------------------


    public function createEventNewProduct(Product $product, Supplier $supplier): ApiResponse
    {

        $data = [
            "section"     => "product",
            "description" => "Un nouveau produit {$product->getKitchenName()} a été ajouté .
                            Le fournisseur est {$supplier->getBusiness()->getName()}.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Nouveau produit : {$product->getKitchenName()}.",
            "dueDate"     => $this->now
        ];
        return $this->ApiResponse($data);
    }

    //! ----------------------------------------------------------------------------------------


    public function createEventUpdatedProduct(Product $product, Supplier $supplier): ApiResponse
    {

        $data = [
            "section"     => "product",
            "description" => "Le produit: {$product->getKitchenName()} a été mis a jour .
                            Le fournisseur est {$supplier->getBusiness()->getName()}.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Mise a jour du produit {$product->getKitchenName()}.",
            "dueDate"     => $this->now
        ];
        return $this->ApiResponse($data);

    }


    //! ----------------------------------------------------------------------------------------


    public function createEventDeletedProduct($product, Supplier $supplier): ApiResponse
    {

        $data = [
            "section"     => "supplier",
            "description" => "Le produit: {$product->getKitchenName()}  a été supprimé.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Produit: {$product->getKitchenName()} supprimé",
            "dueDate"     => $this->now
        ];
        return $this->ApiResponse($data);
    }
    //! ----------------------------------------------------------------------------------------

    private function ApiResponse($data): ApiResponse
    {
        $event = $this->eventService->createOneEvent($data);
        if (!$event) {
            return ApiResponse::error('Event not created', [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Event created', ['event' => $event], Response::HTTP_CREATED);
    }



}



