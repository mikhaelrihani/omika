<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

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
