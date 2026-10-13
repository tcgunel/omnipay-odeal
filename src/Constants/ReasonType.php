<?php

namespace Omnipay\Odeal\Constants;

/**
 * Refund reason types accepted by POST /vpos/refund (reasonType).
 */
class ReasonType
{
    public const DUPLICATE = 'DUPLICATE';

    public const FRAUDULENT = 'FRAUDULENT';

    public const REQUESTED_BY_CUSTOMER = 'REQUESTEDBYCUSTOMER';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::DUPLICATE,
            self::FRAUDULENT,
            self::REQUESTED_BY_CUSTOMER,
        ];
    }

    public static function isValid(?string $reasonType): bool
    {
        return $reasonType === null || $reasonType === '' || in_array($reasonType, self::all(), true);
    }
}
