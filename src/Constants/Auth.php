<?php

namespace Omnipay\Odeal\Constants;

/**
 * POST /token request constants (OAuth2 client credentials grant).
 */
class Auth
{
    /**
     * The only grant type Ödeal supports.
     */
    public const GRANT_TYPE = 'client_credentials';

    /**
     * The token scope required by the virtual POS API.
     */
    public const SCOPE = 'vpos';
}
