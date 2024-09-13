<?php

namespace App\Security;

use App\Service\JwtTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * JwtAuthenticator is responsible for authenticating users based on JWT tokens.
 */
class JwtAuthenticator extends AbstractAuthenticator
{
    private JWTManager $jwtManager;
    private UserProviderInterface $userProvider;
    private JwtTokenService $JwtTokenService;


    /**
     * JwtAuthenticator constructor.
     *
     * @param JWTManager $jwtManager
     * @param UserProviderInterface $userProvider
     * @param JwtTokenService $JwtTokenService
     */
    public function __construct(JWTManager $jwtManager, UserProviderInterface $userProvider, JwtTokenService $JwtTokenService)
    {
        $this->jwtManager = $jwtManager;
        $this->userProvider = $userProvider;
        $this->JwtTokenService = $JwtTokenService;
    }

    /**
     * Checks whether the current request contains an Authorization header with a Bearer token.
     *
     * @param Request $request
     * @return bool|null
     * 
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     * and will return a 401 Unauthorized response.jwt token not found
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization') && strpos($request->headers->get('Authorization'), 'Bearer ') === 0;
    }

    /**
     * Authenticates the user using the JWT token provided in the Authorization header.
     *
     * @param Request $request
     * @return Passport
     *
     * @throws AuthenticationException
     */
    public function authenticate(Request $request): Passport
    {
        // Extract the JWT credentials from the request
        $jwtCredentials = $this->JwtTokenService->getJwtCredential($request);
        $jwtToken = $jwtCredentials[ 'jwtToken' ];
        if (!$jwtToken) {
            throw new AuthenticationException('JWT token is missing from the request.');
        }

        try {
            // Decode and validate the token
            $decodedPayload = $this->jwtManager->decode(new JwtUserToken($jwtToken));
            $userIdentifier = $decodedPayload[ 'username' ];

            // Load the user from the identifier
            $user = $this->userProvider->loadUserByIdentifier($userIdentifier);

            if (!$user->isEnabled()) {
                throw new AuthenticationException('User is disabled.');
            }

            // Return a SelfValidatingPassport with the user badge
            return new SelfValidatingPassport(new UserBadge($userIdentifier, function ($userIdentifier) {
                return $this->userProvider->loadUserByIdentifier($userIdentifier);
            }));

        } catch (JWTDecodeFailureException $e) {
            // Handle token expiration or other JWT errors
            if ($e->getReason() === JWTDecodeFailureException::EXPIRED_TOKEN) {
                // Extract refresh token
                $refreshToken = $this->JwtTokenService->getRefreshTokenFromRequest($request);
                if (!$refreshToken) {
                    throw new AuthenticationException('No refresh token found. Please log in again.');
                }

                // Get the refresh token entity
                $refreshTokenEntity = $this->JwtTokenService->getRefreshTokenEntity($refreshToken);

                if ($refreshTokenEntity && $refreshTokenEntity->isRevoked()) {
                    throw new AuthenticationException('Refresh token has been revoked. User access is blocked.');
                }

                // Check if the refresh token is expired
                if ($refreshTokenEntity && !$refreshTokenEntity->isValid()) {
                    throw new AuthenticationException('Refresh token is expired. Please log in again.');
                }

                // Refresh the JWT token
                $newJwtToken = $this->JwtTokenService->refreshJWTToken($refreshToken);
                if (!$newJwtToken) {
                    throw new AuthenticationException('Failed to refresh JWT token.');
                }

                // Retry with the new JWT token
                $request->headers->set('Authorization', "Bearer $newJwtToken");
                return $this->authenticate($request); // Retry authentication with the new token
            }

            // For other JWTDecodeFailureExceptions, rethrow the exception
            throw new AuthenticationException('Invalid JWT Token: ' . $e->getMessage());
        }

    }


    /**
     * Handles authentication failure.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }

    /*
     * This method is automatically called when authentication was successful, right after the jwtManager of lexikBundle used the method decode. 
     * 
     * Handles successful authentication.
     * @param Request $request
     * @param TokenInterface $token
     * @param string $firewallName
     * @return Response|null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    /*
     * This method is called when authentication is required but the client
     * hasn't provided any credentials (e.g., no JWT token in the Authorization header).
     * It is designed to trigger a 401 Unauthorized response when no authentication
     * attempt has been made (i.e., credentials are missing, not invalid).
     */
    public function start(): Response
    {
        return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
    }


}
