<?php
namespace App\Service;

use App\Entity\user\UserLogin;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class TokenService
{
    private RefreshTokenManagerInterface $refreshTokenManager;
    private JWTManager $jwtManager;
    private UserProviderInterface $userProvider;

    public function __construct(
        RefreshTokenManagerInterface $refreshTokenManager,
        JWTManager $jwtManager,
        UserProviderInterface $userProvider
    ) {
        $this->refreshTokenManager = $refreshTokenManager;
        $this->jwtManager = $jwtManager;
        $this->userProvider = $userProvider;
    }

    public function validateAndRefreshToken(Request $request): ?Response
    {
        $authorizationHeader = $request->headers->get('Authorization');

        if ($authorizationHeader && strpos($authorizationHeader, 'Bearer ') === 0) {
            $jwtToken = substr($authorizationHeader, 7);

            if ($this->isTokenExpired($jwtToken)) {
                $refreshToken = $request->cookies->get('refresh_token');

                if ($refreshToken) {
                    $newJwtToken = $this->handleRefreshToken($refreshToken);

                    if ($newJwtToken) {
                        // Create the response with the new token in a secure cookie
                        return $this->createJwtCookieResponse($newJwtToken);
                    } else {
                        throw new AuthenticationException('Token refresh failed');
                    }
                } else {
                    throw new AuthenticationException('Refresh token missing');
                }
            }
        }

        return null; // Token still valid, no need to refresh
    }

    private function isTokenExpired(string $jwtToken): bool
    {
        $decodedToken = $this->jwtManager->parse($jwtToken);
        $expiration = $decodedToken[ 'exp' ] ?? null;

        if (!$expiration) {
            return true;
        }

        $expiryDate = \DateTime::createFromFormat('U', $expiration);
        return $expiryDate <= new \DateTime();
    }

    public function handleRefreshToken(string $refreshToken): ?string
    {
        // Retrieve the refresh token entity from the database
        $refreshTokenEntity = $this->refreshTokenManager->get($refreshToken);

        if ($refreshTokenEntity === null || $refreshTokenEntity->getValid() <= new \DateTime()) {
            return null; // Invalid or expired refresh token
        }

        // Fetch the username stored in the refresh token entity
        $username = $refreshTokenEntity->getUsername();

        // Load the user using the UserProvider
        $user = $this->userProvider->loadUserByIdentifier($username);

        if (!$user instanceof UserLogin || !$user->isEnabled()) {
            return null; // Invalid or disabled user
        }

        // Generate a new JWT token for the user
        return $this->jwtManager->create($user);
    }

    private function createJwtCookieResponse(string $jwtToken): JsonResponse
    {
        // Creating a JsonResponse that contains a secure HttpOnly cookie with the JWT token
        $response = new JsonResponse(['message' => 'Token refreshed successfully']);

        // Set the JWT token as an HttpOnly, Secure cookie
        $cookie = new Cookie(
            'jwt_token', // Cookie name
            $jwtToken, // JWT token
            time() + 3600, // Expiration time (1 hour for example)
            '/', // Path
            null, // Domain (null for any domain)
            true, // Secure flag (only send cookie over HTTPS)
            true, // HttpOnly flag (only accessible via HTTP, not JS)
            false, // Raw flag (whether the value is raw or URL encoded)
            Cookie::SAMESITE_STRICT // SameSite option to prevent CSRF
        );

        // Add cookie to the response
        $response->headers->setCookie($cookie);

        return $response;
    }
}
