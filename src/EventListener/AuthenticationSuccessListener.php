<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class AuthenticationSuccessListener
{ 
    /**
     * if the route is api_login_check, add the refresh token to a HttpOnly cookie.
     */
    public function setRefreshTokenCookie(ResponseEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $route = $request->attributes->get('_route');

        if ($route === "api_login_check") {
            $data = json_decode($response->getContent(), true);
            $refreshToken = $data[ 'refresh_token' ];

            if (isset($data[ 'refresh_token' ])) {
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
            // Si la route n'est pas "api_login_check", ne rien faire
        }
    }
}