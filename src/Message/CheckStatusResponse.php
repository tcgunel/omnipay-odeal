<?php

namespace Omnipay\Odeal\Message;

use Omnipay\Odeal\Constants\PaymentStatus;

/**
 * Response of POST /vpos/check-status.
 *
 * isSuccessful() is true only for a COMPLETED payment; the raw status stays
 * available through getPaymentStatus() with helpers for every value.
 */
class CheckStatusResponse extends RemoteAbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getPaymentStatus() === PaymentStatus::COMPLETED;
    }

    /**
     * One of the PaymentStatus constants, normalised.
     */
    public function getPaymentStatus(): ?string
    {
        $status = $this->value('result', 'payment_status');

        return is_string($status) ? PaymentStatus::normalize($status) : null;
    }

    public function isPending(): bool
    {
        return $this->getPaymentStatus() === PaymentStatus::PENDING;
    }

    public function isFailed(): bool
    {
        return $this->getPaymentStatus() === PaymentStatus::FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->getPaymentStatus() === PaymentStatus::CANCELLED;
    }

    public function isNotFound(): bool
    {
        return $this->getPaymentStatus() === PaymentStatus::NOT_FOUND;
    }

    public function isRefunded(): bool
    {
        return $this->getPaymentStatus() === PaymentStatus::REFUND;
    }

    public function isPartiallyRefunded(): bool
    {
        return $this->getPaymentStatus() === PaymentStatus::PARTIAL_REFUND;
    }

    /**
     * Ödeal's transaction id (result.order.id).
     */
    public function getTransactionReference(): ?string
    {
        $id = $this->value('result', 'order', 'id');

        return $id === null ? null : (string) $id;
    }

    public function getReferenceId(): ?string
    {
        $reference = $this->value('result', 'order', 'reference_id');

        return $reference === null ? null : (string) $reference;
    }

    public function getAuthCode(): ?string
    {
        $authCode = $this->value('result', 'order', 'auth_code');

        return $authCode === null ? null : (string) $authCode;
    }

    /**
     * The acquirer result code of the order (result.order.result_code).
     */
    public function getResultCode(): ?string
    {
        $code = $this->value('result', 'order', 'result_code');

        return $code === null ? null : (string) $code;
    }

    public function getBankCode(): ?string
    {
        $code = $this->value('result', 'bank_code');

        return $code === null ? null : (string) $code;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getVpos(): ?array
    {
        $vpos = $this->value('result', 'vpos');

        return is_array($vpos) ? $vpos : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrder(): ?array
    {
        $order = $this->value('result', 'order');

        return is_array($order) ? $order : null;
    }

    public function getMessage(): ?string
    {
        return $this->resultMessage();
    }
}
