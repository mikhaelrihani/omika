<?php

namespace App\Controller\Supplier;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SupplierController extends AbstractController
{
    #[Route('/supplier/supplier', name: 'app_supplier_supplier')]
    public function index(): Response
    {
        return $this->render('supplier/supplier/index.html.twig', [
            'controller_name' => 'SupplierController',
        ]);
    }
}
