<?php
// src/Controller/TestController.php
namespace App\Controller;

use App\Service\JwtTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class TestController extends AbstractController
{
    private JwtTokenService $jwtTokenService;
    public function __construct(JwtTokenService $jwtTokenService)
    {
        $this->jwtTokenService = $jwtTokenService;

    }
    #[Route('/api/test', methods: ['POST'])]
    public function test(Request $request): Response
    {
        $refreshToken=$request->cookies->get('REFRESH_TOKEN');
        $this->jwtTokenService->revokeUserTokenAccess($refreshToken);
        return new Response('Hello Omika');
    }
}
