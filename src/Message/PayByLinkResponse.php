<?php

namespace Omnipay\Odeal\Message;

/**
 * Response of POST /vpos/pay-by-link.
 */
class PayByLinkResponse extends RemoteAbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->resultSuccess();
    }

    /**
     * The hosted payment page the shopper must be sent to.
     */
    public function getCheckout3DUrl(): ?string
    {
        $url = $this->value('result', 'checkout3DUrl');

        return is_string($url) && $url !== '' ? $url : null;
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
