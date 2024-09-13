<?php

namespace App\Service;

use App\Entity\user\UserLogin;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class JwtTokenService
{

    private UserProviderInterface $userProvider;
    private JWTEncoderInterface $jwtEncoder;
    private $apiUrl;
    private RefreshTokenRepository $refreshTokenRepository;
    private EntityManagerInterface $em;

    public function __construct(
        UserProviderInterface $userProvider,
        JWTEncoderInterface $jwtEncoder,
        ParameterBagInterface $params,
        RefreshTokenRepository $refreshTokenRepository,
        EntityManagerInterface $em
    ) {
        $this->userProvider = $userProvider;
        $this->jwtEncoder = $jwtEncoder;
        $this->apiUrl = $params->get('api_url');
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->em = $em;
    }

    /**
     * Extracts the JWT token from the Authorization header and returns it as an array.
     *
     * @param Request $request The HTTP request object.
     *
     * @return array An array containing the JWT token.
     *
     * @throws AuthenticationException If no Authorization header is found or no token is provided.
     */
    public function getJwtCredential(Request $request): array
    {
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $jwtToken = str_replace('Bearer ', '', $authHeader);
            return ['jwtToken' => $jwtToken];
        } else {
            throw new AuthenticationException('No Authorization header found, No API token provided');
        }
    }

    /**
     * Retrieves the User entity based on the JWT token.
     *
     * @param Request $request The HTTP request object.
     *
     * @return UserInterface|null The user object or null if authentication fails.
     *
     * @throws AuthenticationException If the JWT token is invalid.
     */
    public function getUser(Request $request): ?UserInterface
    {
        $jwtCredentials = $this->getJwtCredential($request);
        $jwtToken = $jwtCredentials[ 'jwtToken' ];

        try {
            $payload = $this->jwtEncoder->decode($jwtToken);

            if ($payload === false) {
                throw new AuthenticationException('Invalid JWT Token');
            }

            // Extract user information from the token and load the user
            return $this->userProvider->loadUserByIdentifier($payload[ 'username' ]);
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid JWT Token');
        }
    }


    /**
     * Calls the refresh token endpoint to get a new JWT.
     *
     * @param string $refreshToken The refresh token string.
     *
     * @return string|null The new JWT token or null if the refresh token is invalid.
     */
    public function refreshJWTToken(string $refreshToken): ?string
    {
        $httpClient = HttpClient::create();

        $response = $httpClient->request('POST', $this->apiUrl . '/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => ['refresh_token' => $refreshToken],
        ]);

        if ($response->getStatusCode() === 200) {
            return $response->toArray()[ 'token' ] ?? null;
        }

        return null;
    }

    /**
     * Extracts the refresh token from the request, either from cookies or headers.
     *
     * @param Request $request The HTTP request object.
     *
     * @return string|null The refresh token or null if not found.
     */
    public function getRefreshTokenFromRequest(Request $request): ?string
    {
        return $request->cookies->get('REFRESH_TOKEN');
    }

    /**
     * Sets the refresh token as an HttpOnly cookie in the response.
     * Use the expiration date of the refresh token as the cookie expiration date, to avoid missing the refresh token cookie.
     *
     * @param ResponseEvent $event The response event.
     */
    public function setRefreshTokenCookie(ResponseEvent $event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $route = $request->attributes->get('_route');

        if ($route === "api_login_check") {
            $data = json_decode($response->getContent(), true);
            $refreshToken = $data[ 'refresh_token' ];

            $refreshTokenEntity = $this->refreshTokenRepository->findOneByRefreshToken($refreshToken);
            $expirationDate = $refreshTokenEntity->getValid();
            if (!$expirationDate) {
                throw new \Exception('No expiration date for the refresh token');
            }

            if (isset($data[ 'refresh_token' ])) {
                // Add the refresh token to a secure HttpOnly cookie
                $cookie = new Cookie(
                    'REFRESH_TOKEN',
                    $refreshToken,
                    $expirationDate->getTimestamp(), // Utiliser la date d'expiration du refresh token
                    '/',
                    null,
                    true,
                    true,
                    false,
                    Cookie::SAMESITE_STRICT
                );
                $response->headers->setCookie($cookie);

            } else {
                $response->setContent(json_encode(['error' => 'Refresh token not found']));
                $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    }

    /**
     * Revokes a user's access token by revoking the associated refresh token.
     *
     * @param string $refreshToken The refresh token string.
     *
     * @return string|null Null if the token is invalid, or a confirmation message if the token is revoked.
     */
    public function revokeUserTokenAccess(string $refreshToken): null
    {
        $refreshTokenEntity = $this->refreshTokenRepository->findOneByRefreshToken($refreshToken);

        if ($refreshTokenEntity === null || $refreshTokenEntity->getValid() <= new \DateTime() || $refreshTokenEntity->isRevoked()) {
            return null; // Refresh token is invalid, expired, or already revoked
        }

        $username = $refreshTokenEntity->getUsername();
        $user = $this->userProvider->loadUserByIdentifier($username);

        if (!$user instanceof UserLogin || !$user->isEnabled()) {
            return null; // User is invalid or disabled
        }

        // Revoke the refresh token
        $this->revokeRefreshToken($refreshTokenEntity);
        return null;
    }

    /**
     * Marks a refresh token as revoked in the database.
     *
     * @param object $refreshTokenEntity The refresh token entity to be revoked.
     */
    public function revokeRefreshToken($refreshTokenEntity): void
    {
        $refreshTokenEntity->setRevoked(true);
        $this->em->flush();
    }
    /**
     * Retrieves a refresh token entity by its token string.
     *
     * @param string $refreshToken The refresh token string.
     *
     * @return object|null The refresh token entity or null if not found.
     */
    public function getRefreshTokenEntity(string $refreshToken): ?object
    {
        return $this->refreshTokenRepository->findOneByRefreshToken($refreshToken);
    }
}
