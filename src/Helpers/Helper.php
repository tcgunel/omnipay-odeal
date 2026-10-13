<?php

namespace Omnipay\Odeal\Helpers;

class Helper
{
    /**
     * Normalise a Turkish mobile number to the 5XXXXXXXXX form Ödeal expects.
     *
     * Accepts the usual shapes: "5551234567", "0555 123 45 67",
     * "+90 (555) 123 45 67", "0090 555 123 45 67". Returns null when the
     * number is not a 10-digit mobile number starting with 5.
     */
    public static function normalizePhone(mixed $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 14 && str_starts_with($digits, '0090')) {
            $digits = substr($digits, 4);
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^5\d{9}$/', $digits) === 1 ? $digits : null;
    }
}
