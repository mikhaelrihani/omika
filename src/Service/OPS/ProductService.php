<?php
namespace App\Service\OPS;

use App\Entity\Inventory\Room;
use App\Entity\Inventory\RoomProduct;
use App\Entity\Product\Product;
use App\Entity\Product\ProductType;
use App\Entity\Recipe\Recipe;
use App\Entity\Recipe\Unit;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Supplier\Supplier;
use App\Repository\Product\ProductRepository;
use App\Service\Event\EventService;
use App\Service\Media\FileService;
use App\Service\ValidatorService;
use App\Utils\ApiResponse;
use DateTimeImmutable;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class ProductService
{
    private DateTimeImmutable $now;
    function __construct(
        private EntityManagerInterface $em,
        private ProductRepository $productRepository,
        private ValidatorService $validateService,
        protected SerializerInterface $serializer,
        protected EventService $eventService,
        protected ProductEventService $productEventService,
        protected FileService $fileService,

    ) {
        $this->now = new DateTimeImmutable('today');
    }

    //! ----------------------------------------------------------------------------------------


    /**
     * Récupère un produit par son identifiant.
     *
     * Cette méthode recherche un produit spécifique à partir de son identifiant.
     * Si le produit n'est pas trouvé, une réponse d'erreur est retournée.
     *
     * @param int $id L'identifiant du produit à récupérer.
     *
     * @return ApiResponse Retourne une réponse API avec le produit trouvé ou un message d'erreur si le produit n'existe pas.
     */
    public function getProduct(int $id): ApiResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return ApiResponse::error('Product not found', [], Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success('Product found succesfully', ['product' => $product], Response::HTTP_OK);
    }


    //! ----------------------------------------------------------------------------------------


    /**
     * Récupère la liste de tous les produits disponibles.
     *
     * Cette méthode retourne tous les produits présents dans la base de données.
     * Si aucun produit n'est trouvé, une réponse d'erreur est retournée.
     *
     * @return ApiResponse Retourne une réponse API avec la liste des produits ou un message d'erreur si aucun produit n'est trouvé.
     */
    public function getProducts(): ApiResponse
    {
        $products = $this->productRepository->findAll();
        if (!$products) {
            return ApiResponse::error('Products not found', [], Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success('Products found succesfully', ['products' => $products], Response::HTTP_OK);
    }


    //! ----------------------------------------------------------------------------------------


    /**
     * Crée un nouveau produit à partir des données de la requête.
     *
     * Cette méthode gère la validation des données JSON, la création du produit,
     * la validation de l'entité, l'émission d'un événement pour le nouveau produit,
     * et assure une transaction pour garantir l'intégrité des opérations.
     *
     * @param Request $request La requête HTTP contenant les données du produit en format JSON.
     *
     * @return ApiResponse Retourne une réponse API indiquant le succès ou l'erreur de la création du produit.
     *
     * @throws \Exception En cas d'erreur inattendue pendant le traitement.
     */
    public function createProduct(Request $request): ApiResponse
    {
        $this->em->beginTransaction();
        try {
            $responseData = $this->validateService->validateJson($request);
            if (!$responseData->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseData->getMessage(), null, $responseData->getStatusCode());
            }

            $product = $this->handleProductCreation($responseData);
            if ($product instanceof ApiResponse) {
                $this->em->rollback();
                return $product;
            }

            $responseValidation = $this->validateService->validateEntity($product);
            if (!$responseValidation->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseValidation->getMessage(), $responseValidation->getData()[ "errors" ], $responseValidation->getStatusCode());
            }

            $responseEvent = $this->productEventService->createEventNewProduct($product, $product->getSupplier());
            if (!$responseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseEvent->getMessage(), null, $responseEvent->getStatusCode());
            }

            $this->em->commit();
            $this->em->flush();
            return ApiResponse::success('Product created succesfully', ['product' => $product], Response::HTTP_CREATED);

        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! ----------------------------------------------------------------------------------------

    public function updateProduct(int $id, Request $request): ApiResponse
    {
        $this->em->beginTransaction();
        try {
            $product = $this->productRepository->find($id);
            if (!$product) {
                $this->em->rollback();
                return ApiResponse::error(
                    "There is no product with this id",
                    null,
                    Response::HTTP_BAD_REQUEST
                );
            }

            $data = $this->validateService->validateJson($request);
            if (!$data->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($data->getMessage(), null, Response::HTTP_BAD_REQUEST);
            }

            $product = $this->serializer->deserialize($request->getContent(), Product::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $product]);

            if (!$product) {
                $this->em->rollback();
                return ApiResponse::error("Error while deserializing product", null, Response::HTTP_BAD_REQUEST);
            }

            $responseRelation = $this->handleRelationsOnUpdate($product, $data->getData());
            if (!$responseRelation) {
                $this->em->rollback();
                return ApiResponse::error($responseRelation->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $responseValidation = $this->validateService->validateEntity($product);
            if (!$responseValidation->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseValidation->getMessage(), $responseValidation->getData()[ "errors" ], $responseValidation->getStatusCode());
            }

            $responseEvent = $this->productEventService->createEventUpdatedProduct($product, $product->getSupplier());
            if (!$responseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($responseEvent->getMessage(), $responseEvent->getData(), $responseEvent->getStatusCode());
            }

            $this->em->commit();
            $this->em->flush();

            return ApiResponse::success("Succesfully updated {$product->getKitchenName()}", ["product" => $product], Response::HTTP_OK);
        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_BAD_REQUEST);
        }

    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Supprime un produit en fonction de son identifiant.
     *
     * Cette méthode supprime un produit de la base de données. Avant la suppression, elle vérifie si le produit existe,
     * supprime toutes ses relations associées, crée un événement de suppression, puis supprime le produit. Si une erreur se produit
     * à n'importe quelle étape, la transaction est annulée et une réponse d'erreur est renvoyée.
     *
     * @param int $id L'identifiant du produit à supprimer.
     *
     * @return ApiResponse Retourne une réponse API indiquant si la suppression a réussi ou échoué.
     */
    public function deleteProduct(int $id): ApiResponse
    {
        $this->em->beginTransaction();
        try {
            $product = $this->productRepository->find($id);
            if (!$product) {
                $this->em->rollback();
                return ApiResponse::error("There is no product with this id", null, Response::HTTP_BAD_REQUEST);
            }


            $responseRelation = $this->handleRelationsOnDelete($product);
            if (!$responseRelation) {
                $this->em->rollback();
                return ApiResponse::error($responseRelation->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
            }


            $ResponseEvent = $this->productEventService->createEventDeletedProduct($product);
            if (!$ResponseEvent->isSuccess()) {
                $this->em->rollback();
                return ApiResponse::error($ResponseEvent->getMessage(), null, $ResponseEvent->getStatusCode());
            }

            $this->em->remove($product);
            $this->em->commit();
            $this->em->flush();

            return ApiResponse::success("Product deleted succesfully", null, Response::HTTP_OK);

        } catch (Exception $e) {
            $this->em->rollback();
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    //! ----------------------------------------------------------------------------------------

    /**
     * Gère la création d'un produit en fonction des données de la réponse API.
     *
     * @param ApiResponse $responseData Les données de la réponse API nécessaires à la création du produit.
     *
     * @return Product|ApiResponse Retourne une instance de `Product` si la création est réussie.
     *                              Retourne une instance de `ApiResponse` en cas d'erreur (ex. si le produit existe déjà).
     *
     * @throws \Exception Si une des entités requises (Unit, Supplier, ProductType) n'est pas trouvée.
     */
    private function handleProductCreation(ApiResponse $responseData): Product|ApiResponse
    {
        $slug = $this->fileService->slugify($responseData->getData()[ 'commercialName' ]);
        $unit = $this->em->getRepository(Unit::class)->find($responseData->getData()[ 'unit' ]);
        $supplier = $this->em->getRepository(Supplier::class)->find($responseData->getData()[ 'supplier' ]);
        $type = $this->em->getRepository(ProductType::class)->find($responseData->getData()[ 'type' ]);
        $kitchenName = $responseData->getData()[ 'kitchenName' ];

        $isProductExist = $this->isProductExist($kitchenName, $supplier->getId());
        if ($isProductExist) {
            return ApiResponse::error("The supplier already have this product listed with kitchenName : {$kitchenName} ", null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $product = (new Product())
            ->setCommercialName($responseData->getData()[ 'commercialName' ])
            ->setKitchenName($responseData->getData()[ 'kitchenName' ])
            ->setSlug($slug)
            ->setPrice($responseData->getData()[ 'price' ])
            ->setConditionning($responseData->getData()[ 'conditionning' ])
            ->setUnit($unit)
            ->setSupplier($supplier)
            ->setType($type);

        $supplier->addProduct($product);

        $this->em->persist($product);
        return $product;

    }


    //! ----------------------------------------------------------------------------------------


    /**
     * Met à jour les relations associées à un produit.
     *
     * Cette méthode gère la mise à jour des relations entre un produit et ses entités associées comme l'unité, le type
     * et les pièces (rooms). Elle permet de :
     * - Mettre à jour l'unité (`Unit`) si elle est spécifiée.
     * - Mettre à jour le type (`ProductType`) si elle est spécifiée.
     * - Mettre à jour les pièces (`Room`) liées au produit. Les relations existantes avec les pièces sont supprimées
     *   avant d'ajouter les nouvelles relations.
     *
     * @param Product $product L'entité du produit à mettre à jour.
     * @param array $data Les données mises à jour contenant éventuellement des clés pour 'unit', 'type' et 'room'.
     *
     * @return ApiResponse Retourne un objet `ApiResponse` indiquant le succès ou l'erreur de l'opération.
     *
     * @throws Exception Si une erreur survient pendant le processus de mise à jour des relations.
     *
     * @todo Vérifier les doublons sur les chambres et étagères.
     * @todo Créer un Cronjob pour envoyer un événement de rappel afin de mettre à jour les étagères des produits si égal à zéro.
     */
    private function handleRelationsOnUpdate(Product $product, array $data): ApiResponse
    {
        //@todo verifier les doublons sur les chambres et étageres
        //@todo créer un Cronjob pour envoyer un event de rappel pour mettre a jour les roomsshelf des produits si egal a zero
        //@todo mettre a jour la creation de product avec room
        try {
            if (isset($data[ 'unit' ])) {
                $unit = $this->em->getRepository(Unit::class)->find($data[ 'unit' ]);
                $product->setUnit($unit);
            }
            if (isset($data[ 'type' ])) {
                $type = $this->em->getRepository(ProductType::class)->find($data[ 'type' ]);
                $product->setType($type);
            }
            if (isset($data[ 'roomProducts' ])) {
                $currentRoomProducts = $product->getRoomProducts();
                foreach ($currentRoomProducts as $roomProduct) {
                    $product->removeRoomProduct($roomProduct);
                }
                foreach ($data[ 'roomProducts' ] as $roomData) {
                    $room = $this->em->getRepository(Room::class)->find($roomData[ 'room' ]);
                    if ($room) {
                        $roomProduct = new RoomProduct();
                        $roomProduct->setRoom($room);
                        $roomProduct->setRoomShelf($roomData[ 'roomShelf' ]);
                        $roomProduct->setProduct($product);
                        $product->addRoomProduct($roomProduct);
                    }
                }
            }

            $this->em->persist($product);


            return ApiResponse::success("Relations updated succesfully", null, Response::HTTP_OK);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    //! ----------------------------------------------------------------------------------------

    private function handleRelationsOnDelete(Product $product): ApiResponse
    {
        try {
            $product->setUnit(null);
            $product->setType(null);
            $product->setRupture(null);
            $product->getSupplier()->removeProduct($product);

            return ApiResponse::success("Relations removed succesfully", null, Response::HTTP_OK);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    //! ----------------------------------------------------------------------------------------

    /**
     * Récupère les produits associés à un fournisseur donné.
     *
     * @param int $id L'identifiant du fournisseur.
     *
     * @return ApiResponse Retourne une réponse API contenant les produits du fournisseur.
     *                     - Retourne une erreur 404 si le fournisseur n'est pas trouvé.
     *                     - Retourne une erreur 404 si aucun produit n'est associé au fournisseur.
     *                     - Retourne une réponse 200 avec les produits si trouvés.
     */
    public function getProductBySupplier(int $id): ApiResponse
    {
        $supplier = $this->em->getRepository(Supplier::class)->find($id);
        if (!$supplier) {
            return ApiResponse::error('Supplier not found', [], Response::HTTP_NOT_FOUND);
        }

        $products = $supplier->getProducts();
        if ($products->isEmpty()) {
            return ApiResponse::error('Products not found', [], Response::HTTP_NOT_FOUND);
        }

        return ApiResponse::success('Products found', ['products' => $products], Response::HTTP_OK);
    }

    //! ----------------------------------------------------------------------------------------

    public function toggleFavorite(int $productId): ApiResponse
    {
        try {
            $currentProduct = $this->productRepository->find($productId);
            if (!$currentProduct) {
                return ApiResponse::error('Product not found', [], Response::HTTP_NOT_FOUND);
            }
            $currentProduct->setSupplierFavorite(!$currentProduct->isSupplierFavorite());

            $KitchenName = $currentProduct->getKitchenName();
            $suppliers = $this->em->getRepository(Supplier::class)->findAll();
            foreach ($suppliers as $supplier) {
                $product = $supplier->getProducts()->filter(function ($product) use ($KitchenName) {
                    return $product->getKitchenName() === $KitchenName;
                })->first();
                if ($product && $currentProduct->isSupplierFavorite() === true) {
                    $product->setSupplierFavorite(false);
                }
            }

            $this->em->flush();
            return ApiResponse::success('Product favorite status updated succesfully', null, Response::HTTP_OK);

        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Vérifie si un produit existe pour un fournisseur donné et un nom de cuisine spécifique.
     *
     * @param string $kitchenName Le nom de cuisine du produit à rechercher.
     * @param int    $supplierId  L'identifiant du fournisseur associé au produit.
     *
     * @return bool Retourne `true` si le produit existe, sinon `false`.
     */
    private function isProductExist(string $kitchenName, int $supplierId): bool
    {
        $Product = $this->productRepository->isProductExist($supplierId, $kitchenName);
        return count($Product) > 0;
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Récupère les fournisseurs associés à un produit donné par son identifiant.
     *
     * @param int $id L'identifiant du produit.
     *
     * @return ApiResponse Retourne une réponse API contenant les fournisseurs du produit ou un message d'erreur si aucun fournisseur n'est trouvé.
     *
     * @throws \Doctrine\ORM\EntityNotFoundException Si le produit avec l'identifiant donné n'existe pas.
     */
    public function getProductSuppliers(int $id): ApiResponse
    {
        $product = $this->productRepository->find($id);
        $suppliers = $this->productRepository->findProductSuppliers($product);
        if (!$suppliers) {
            return ApiResponse::error('Suppliers not found', [], Response::HTTP_NOT_FOUND);
        }
        return ApiResponse::success('Suppliers found', ['suppliers' => $suppliers], Response::HTTP_OK);
    }


    //! ----------------------------------------------------------------------------------------

    /**
     * Recherche des produits en fonction du nom de cuisine ou du nom commercial fourni dans la requête.
     *
     * @param Request $request La requête HTTP contenant le paramètre de recherche 'query'.
     *
     * @return ApiResponse Retourne une réponse API avec les produits trouvés ou un message d'erreur.
     *
     * @throws \Doctrine\ORM\Query\QueryException Si une erreur survient lors de la création ou de l'exécution de la requête.
     */
    public function searchProduct(Request $request): ApiResponse
    {
        $query = $request->query->get('query');
        if (empty($query)) {
            return ApiResponse::error("Query parameter is required", null, Response::HTTP_BAD_REQUEST);
        }

        // Search for products with matching kitchen/commercial names
        $products = $this->em->getRepository(Product::class)->createQueryBuilder('p')
            ->where('LOWER(p.kitchenName) LIKE :query OR LOWER(p.commercialName) LIKE :query')
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->setMaxResults(10) // Limit the number of results for performance
            ->getQuery()
            ->getResult();

        // If no products are found
        if (empty($products)) {
            return ApiResponse::error("No products found", null, Response::HTTP_NOT_FOUND);
        }

        // Format response data
        $formatedProducts = array_map(function ($product) {
            return [
                'id'             => $product->getId(),
                'kitchenName'    => $product->getKitchenName(),
                'commercialName' => $product->getCommercialName(),
            ];
        }, $products);

        return ApiResponse::success("Products found", ["products" => $formatedProducts], Response::HTTP_OK);
    }

    //! ----------------------------------------------------------------------------------------

    /**
     * Trouve les recettes associées à un produit donné.
     *
     * @param int $id L'identifiant du produit.
     *
     * @return ApiResponse Retourne une réponse API avec les recettes trouvées ou un message d'erreur si aucune recette n'est trouvée.
     *
     * @throws \Doctrine\ORM\Query\QueryException Si une erreur survient lors de la création de la requête.
     */
    public function getProductRecipes(int $id): ApiResponse
    {

        $product = $this->productRepository->find($id);
        if (!$product) {
            return ApiResponse::error('Product not found', [], Response::HTTP_NOT_FOUND);
        }


        $recipes = $this->em->getRepository(Recipe::class)->findProductRecipes($product);
        if (!$recipes) {
            return ApiResponse::error('Recipes not found', [], Response::HTTP_NOT_FOUND);
        }

        $data = array_map(fn($recipe) => [
            'id'   => $recipe[ 'id' ],
            'name' => $recipe[ 'name' ],
            'path' => $recipe[ 'path' ],
        ], $recipes);

        return ApiResponse::success('Recipes found', ['recipes' => $data], Response::HTTP_OK);
    }

}



