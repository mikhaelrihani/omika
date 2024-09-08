<?php

namespace App\Security;

use App\Entity\user\User;
use App\Entity\user\UserLogin;
use App\Service\TokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator implements AuthenticatorInterface
{
    private JWTEncoderInterface $jwtEncoder;
    private TokenService $tokenService;

    public function __construct(JWTEncoderInterface $jwtEncoder, TokenService $tokenService)
    {
        $this->jwtEncoder = $jwtEncoder;
        $this->tokenService = $tokenService;
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
            $data = $this->jwtEncoder->decode($jwtToken);

            if ($data === false) {
                throw new AuthenticationException('Invalid JWT Token');
            }
            // Extract user information from the token and load the user
            return $userProvider->loadUserByIdentifier($data[ 'username' ]);
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid JWT Token');
        }
    }

    /**
     * Check if User is Enabled.
     * If the user is disabled, return false to stop the authentication process immediately.
     */
    public function isEnabled(UserLogin $user): bool
    {
        if (!$user->isEnabled()) {
            // Stop the process if the user is not enabled
            throw new AuthenticationException('User is disabled.', Response::HTTP_UNAUTHORIZED);
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $jwtCredentials = $this->getJwtCredential($request);
        $jwtToken = $jwtCredentials[ 'jwtToken' ];

        // Validate and possibly refresh the JWT token
        if ($this->tokenService->isTokenExpired($jwtToken)) {
            $response = $this->tokenService->validateAndRefreshToken($request);
            if ($response) {
                $newJwtToken = $response->headers->get('Set-Cookie');
                if ($newJwtToken) {
                    $request->headers->set('Authorization', 'Bearer ' . $newJwtToken);
                }
            }
            return new SelfValidatingPassport(new UserBadge($userIdentifier));
        }

        throw new AuthenticationException('No Authorization header found, No API token provided');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }

    /*
        * This method is called when authentication executed and was successful.

        */
    public function onAuthenticationSuccess(Request $request, PassportInterface $passport, string $providerKey): ?Response
    {
        // Allow the request to continue normally
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
