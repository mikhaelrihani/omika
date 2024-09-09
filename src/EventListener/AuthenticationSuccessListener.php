<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\RouterInterface;

class AuthenticationSuccessListener
{
    private $router;
    private $logger;
    public function __construct(RouterInterface $router, LoggerInterface $logger)
    {
        $this->router = $router;
        $this->logger = $logger;
    }
    /**
     * On authentication success, add the refresh token to a HttpOnly cookie.
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $path = $request->getPathInfo();

        // Vérifiez si le chemin est un chemin API
        if (strpos($path, '/api') === 0) {
            try {
                $this->router->match($path);
            } catch (\Exception $e) {
                $event->setResponse(new Response('Not Found', Response::HTTP_NOT_FOUND));
                return;
            }
        }
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