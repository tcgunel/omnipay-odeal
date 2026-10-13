<?php

namespace Omnipay\Odeal\Constants;

/**
 * Payment statuses of POST /vpos/check-status (result.payment_status).
 */
class PaymentStatus
{
    public const PENDING = 'PENDING';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    public const CANCELLED = 'CANCELLED';

    public const NOT_FOUND = 'NOTFOUND';

    public const REFUND = 'REFUND';

    public const PARTIAL_REFUND = 'PARTIAL_REFUND';

    /**
     * The API has been observed answering the partial-refund status both with
     * and without the underscore ("PARTIAL REFUND" / "PARTIAL_REFUND"); both
     * are normalised to PARTIAL_REFUND.
     */
    private const ALIASES = [
        'PARTIAL REFUND' => self::PARTIAL_REFUND,
    ];

    public static function normalize(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $status = strtoupper(trim($status));

        return self::ALIASES[$status] ?? $status;
    }
}
