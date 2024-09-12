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

class JwtAuthenticator extends AbstractAuthenticator
{
    private JWTManager $jwtManager;
    private UserProviderInterface $userProvider;
    private JwtTokenService $JwtTokenService;



    public function __construct(JWTManager $jwtManager, UserProviderInterface $userProvider, JwtTokenService $JwtTokenService)
    {
        $this->jwtManager = $jwtManager;
        $this->userProvider = $userProvider;
        $this->JwtTokenService = $JwtTokenService;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization') && strpos($request->headers->get('Authorization'), 'Bearer ') === 0;
    }


    public function authenticate(Request $request): Passport
    {
        // Extract the JWT credentials from the request
        $jwtCredentials = $this->JwtTokenService->getJwtCredential($request);
        $jwtToken = $jwtCredentials[ 'jwtToken' ];

        // Check if the token is expired
        if ($this->JwtTokenService->isTokenExpired($jwtToken)) {
            // Handle token expiration
            $refreshToken = $this->JwtTokenService->getRefreshTokenFromRequest($request);
            $newJwtToken = $this->JwtTokenService->refreshJWTToken($refreshToken);

            if ($newJwtToken) {
                // Retry with new JWT
                $request->headers->set('Authorization', "Bearer $newJwtToken");
                return $this->authenticate($request);
            }

            throw new AuthenticationException('Refresh token expired or invalid. Please log in again.');
        }

        try {
            // Decode and validate the token
            $decodedPayload = $this->jwtManager->decode(new JwtUserToken($jwtToken));

            // Extract the user identifier from the decoded payload
            $userIdentifier = $decodedPayload[ 'username' ];

            // Check if user is enabled
            $user = $this->userProvider->loadUserByIdentifier($userIdentifier);
            if (!$user->isEnabled()) {
                throw new AuthenticationException('User is disabled.');
            }

            // Create UserBadge 
            $userBadge = new UserBadge($userIdentifier, function ($userIdentifier) {
                return $this->userProvider->loadUserByIdentifier($userIdentifier);
            });

            // Return SelfValidatingPassport with the user badge
            return new SelfValidatingPassport($userBadge);

        } catch (JWTDecodeFailureException $e) {
            throw new AuthenticationException('Invalid JWT Token: ' . $e->getMessage());
        }
    }


    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }

    /*
        * This method is called when authentication executed and was successful.

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
