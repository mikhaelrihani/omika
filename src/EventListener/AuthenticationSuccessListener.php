<?php
namespace App\EventListener;

use App\Service\JwtTokenService; // Assurez-vous que cette ligne est correcte
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class AuthenticationSuccessListener
{
    private JwtTokenService $jwtTokenService;

    public function __construct(JwtTokenService $jwtTokenService)
    {
        $this->jwtTokenService = $jwtTokenService;
    }
    /**
     * On kernelResponse : if the route is api_login_check, add the refresh token to a HttpOnly cookie.
     */
    public function setRefreshTokenCookie(ResponseEvent $event)
    {
       $this->jwtTokenService->setRefreshTokenCookie($event);
    }
}