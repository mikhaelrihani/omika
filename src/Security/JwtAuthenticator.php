<?php

namespace App\Security;

use App\Entity\user\User;
use App\Entity\user\UserLogin;
use App\Service\TokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator
{
    private JWTEncoderInterface $jwtEncoder;
    private TokenService $tokenService;
    private JWTManager $jwtManager;
    private UserProviderInterface $userProvider;


    public function __construct(JWTEncoderInterface $jwtEncoder, TokenService $tokenService, JWTManager $jwtManager, UserProviderInterface $userProvider)
    {
        $this->jwtEncoder = $jwtEncoder;
        $this->tokenService = $tokenService;
        $this->jwtManager = $jwtManager;
        $this->userProvider = $userProvider;
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

    /**
     * This extracts the JWT token from the Authorization header
     */
    public function getJwtCredential(Request $request): array
    {
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $jwtToken = str_replace('Bearer ', '', $authHeader);

            return [
                'jwtToken' => $jwtToken,
            ];
        } else {
            throw new AuthenticationException('No Authorization header found, No API token provided');
        }
    }

    /**
     * Retrieve the User entity based on the JWT token
     * @param \Symfony\Component\Security\Core\User\UserProviderInterface $userProvider
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @throws \Symfony\Component\Security\Core\Exception\AuthenticationException
     * @return User
     */
    public function getUser(UserProviderInterface $userProvider, Request $request): ?UserInterface
    {
        $jwtCredentials = $this->getJwtCredential($request);
        $jwtToken = $jwtCredentials[ 'jwtToken' ];

        try {
            $payload = $this->jwtEncoder->decode($jwtToken);

            if ($payload === false) {
                throw new AuthenticationException('Invalid JWT Token');
            }
            // Extract user information from the token and load the user
            return $userProvider->loadUserByIdentifier($payload[ 'username' ]);
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid JWT Token');
        }
    }

    /**
     * Check if User is Enabled.
     * If the user is disabled, return false to stop the authentication process immediately.
     */
    public function isEnabled(UserInterface $user): bool
    {
        if (!$user instanceof UserLogin) {
            throw new AuthenticationException('Invalid User');
        }
        if (!$user->isEnabled()) {
            // Stop the process if the user is not enabled
            throw new AuthenticationException('User is disabled.', Response::HTTP_UNAUTHORIZED);
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        // Extract the JWT credentials from the request
        $jwtCredentials = $this->getJwtCredential($request);
        $jwtToken = $jwtCredentials[ 'jwtToken' ];

        // Create a new JWTUserToken object and pass it to the decode method
        $jwtUserToken = new JwtUserToken($jwtToken);

        // Decode the token to validate and retrieve the payload to get the user identifier
        try {
            $decodedPayload = $this->jwtManager->decode($jwtUserToken);
        } catch (JWTDecodeFailureException $e) {
            // Handle decoding failure, e.g., expired token
            if ($e->getCode() === JWTDecodeFailureException::EXPIRED_TOKEN) {
                // Call the refresh token route to get a new JWT 
                $refreshToken = $this->getRefreshTokenFromRequest($request);
                $newJwtToken = $this->refreshJWTToken($refreshToken);

                //!cas ou le refreshtoken est expired 
                
               

                if ($newJwtToken) {
                    // Set the new JWT in the request headers
                    $request->headers->set('Authorization', 'Bearer {$newJwtToken}');
                    // Retry authentication with the new JWT
                    $jwtUserToken = new JwtUserToken($newJwtToken);
                    $decodedPayload = $this->jwtManager->decode($jwtUserToken);
                } else {
                    throw new AuthenticationException('Failed to refresh token.');
                }
            } else {
                throw $e; // Rethrow exception if it's not related to token expiration
            }
        }

        // Extract the user identifier
        $userIdentifier = $decodedPayload[ 'username' ];

        // Return the Passport object with user details
        return new SelfValidatingPassport(new UserBadge($userIdentifier));
    }

    /**
     * Call the refresh token endpoint to get a new JWT.
     */
    private function refreshJWTToken(string $refreshToken): ?string
    {
        $httpClient = HttpClient::create(); // Create an HTTP client instance

        $response = $httpClient->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => ['refresh_token' => $refreshToken],
        ]);

        if ($response->getStatusCode() === 200) {
            return $response->toArray()[ 'token' ] ?? null;
        }

        return null;
    }

    /**
     * Extract the refresh token from the request.
     */
    private function getRefreshTokenFromRequest(Request $request): ?string
    {
        // Extract the refresh token from cookies or headers
        return $request->cookies->get('REFRESH_TOKEN');
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
