<?php

namespace Omnipay\Odeal\Constants;

/**
 * Ödeal environment endpoints.
 *
 * Every environment exposes two different base URLs:
 *
 *  - the token base URL answers POST /token (authentication), and
 *  - the gateway base URL hosts the /vpos operations (payment, cancel,
 *    refund, status, ...).
 *
 * Sandbox and production credentials are separate; sandbox credentials do
 * not work on production and vice versa.
 */
class Endpoint
{
    public const TOKEN_TEST = 'https://auth-sandbox.odeal.com/api/v1/';

    public const TOKEN_LIVE = 'https://auth.odeal.com/api/v1/';

    public const GATEWAY_TEST = 'https://api-stg.odeal.com/vpos/';

    public const GATEWAY_LIVE = 'https://api.odeal.com/vpos/';

    public static function token(bool $testMode): string
    {
        return $testMode ? self::TOKEN_TEST : self::TOKEN_LIVE;
    }

    public static function gateway(bool $testMode): string
    {
        return $testMode ? self::GATEWAY_TEST : self::GATEWAY_LIVE;
    }
}
