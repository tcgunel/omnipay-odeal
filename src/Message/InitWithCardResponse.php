<?php

namespace Omnipay\Odeal\Message;

/**
 * Response of POST /vpos/init-with-card.
 */
class InitWithCardResponse extends RemoteAbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->resultSuccess() && ! $this->getForce3DPayment();
    }

    /**
     * True when the issuer demands a 3D verification for this payment; the
     * charge must be restarted through the 3D flow.
     */
    public function getForce3DPayment(): bool
    {
        return $this->value('result', 'force3DPayment') === true;
    }

    /**
     * Ödeal's transaction id - cancel, refund and status calls operate on it.
     */
    public function getTransactionReference(): ?string
    {
        return $this->resultId();
    }

    public function getMessage(): ?string
    {
        return $this->resultMessage();
    }
}
