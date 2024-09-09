<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class AuthenticationSuccessListener
{
    /**
     * On authentication success, add the refresh token to a HttpOnly cookie.
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();
        $data = json_decode($response->getContent(), true);
        $refreshToken = $data[ 'refresh_token' ];

        if ($refreshToken) {
            // Add the refresh token to a secure HttpOnly cookie
            $response->headers->setCookie(
                new Cookie(
                    'REFRESH_TOKEN', // Cookie name
                    $refreshToken, // refresh token string
                    time() + 3600, // Expiration time (1 hour for example)
                    '/', // Path
                    null, // Domain (null for any domain)
                    true, // Secure flag (only send cookie over HTTPS)
                    true, // HttpOnly flag (only accessible via HTTP, not JS)
                    false, // Raw flag (whether the value is raw or URL encoded)
                    Cookie::SAMESITE_STRICT // SameSite option to prevent CSRF
                )
            );
        } else {
            $response->setContent(json_encode(['error' => 'Refresh token not found']));
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        
    }
}