<?php

namespace Omnipay\Odeal\Message;

/**
 * Response of POST /vpos/cancel.
 */
class CancelResponse extends RemoteAbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->resultSuccess();
    }

    public function getMessage(): ?string
    {
        return $this->resultMessage();
    }
}
