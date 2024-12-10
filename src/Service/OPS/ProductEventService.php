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
    private string $timeString;
    function __construct(
        private EntityManagerInterface $em,
        protected EventService $eventService,
    ) {
        $this->now = new DateTimeImmutable('today');
        $this->timeString = (new DateTimeImmutable('now'))->format('H:i:s');
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
            "title"       => "Nouveau produit : {$product->getKitchenName()} à {$this->timeString}.",
            "dueDate"     => ($this->now)->format('Y-m-d'),
        ];
        return $this->ApiEventResponse($data);
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
            "title"       => "Mise a jour du produit {$product->getKitchenName()} à {$this->timeString}.",
            "dueDate"     => ($this->now)->format('Y-m-d'),
        ];

        return $this->ApiEventResponse($data);

    }


    //! ----------------------------------------------------------------------------------------


    public function createEventDeletedProduct($product): ApiResponse
    {

        $data = [
            "section"     => "supplier",
            "description" => "Le produit: {$product->getKitchenName()}  a été supprimé.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Produit: {$product->getKitchenName()}supprimé à {$this->timeString}",
            "dueDate"     => ($this->now)->format('Y-m-d'),
        ];
        return $this->ApiEventResponse($data);
    }
    //! ----------------------------------------------------------------------------------------

    private function ApiEventResponse(array $data, $cronJob = false): ApiResponse
    {
        $event = $this->eventService->createOneEvent($data, $cronJob);

        if (!$event->isSuccess()) {
            return ApiResponse::error('Event not created', ["error" => $event->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return ApiResponse::success('Event created', ['event' => $event], Response::HTTP_CREATED);
    }

    //! ----------------------------------------------------------------------------------------

    public function createEventProductsWithoutShelf(int $count): ApiResponse
    {
        $data = [
            "section"     => "product",
            "description" => "Il y a {$count} produits sans étagère.",
            "type"        => "info",
            "side"        => "office",
            "title"       => "Produits sans étagère. à {$this->timeString}.",
            "dueDate"     => ($this->now)->format('Y-m-d'),
        ];

        return $this->ApiEventResponse($data, true);

    }

}



