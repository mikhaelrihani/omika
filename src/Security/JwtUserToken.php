<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * We created the custom JWTUserToken because, in the context of JWT handling, the standard 
 * Symfony TokenInterface does not provide a built-in way to handle JWT tokens, which are 
 * simple strings. The default TokenInterface expects complex objects with credentials and user 
 * information, but JWT tokens are just strings passed around for authentication.
 *
 * The parse and decode methods require a string representation of the JWT token for processing. 
 * However, the TokenInterface doesn't include a method to retrieve a string-based token 
 * from its instance. Therefore, we had to create a custom JWTUserToken that implements TokenInterface 
 * and includes a method to return the actual token string (via getCredentials()).
 * 
 */
class JwtUserToken extends AbstractToken
{
    private string $jwtToken;

    public function __construct(string $jwtToken, array $roles = [])
    {
        parent::__construct($roles);

        $this->jwtToken = $jwtToken;
     
    }

    /**
     * Returns the JWT token as credentials.
     */
    public function getCredentials(): string
    {
        return $this->jwtToken;
    }
}
