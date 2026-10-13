<?php

namespace Omnipay\Odeal\Message;

/**
 * Response of POST /vpos/init-3d.
 *
 * While the 3D challenge is outstanding the payment is not complete:
 * isRedirect() is true and getHtmlContent() carries the challenge page. The
 * final result arrives on the returnUrl callback (or through checkStatus()).
 */
class Init3DResponse extends RemoteAbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->resultSuccess() && ! $this->isRedirect();
    }

    public function isRedirect(): bool
    {
        return $this->resultSuccess() && $this->getThreeDFormHtml() !== null;
    }

    /**
     * The self-contained 3D Secure challenge page to echo to the browser.
     */
    public function getHtmlContent(): ?string
    {
        return $this->getThreeDFormHtml();
    }

    public function getThreeDFormHtml(): ?string
    {
        $html = $this->value('result', 'threeDFormHtml');

        return is_string($html) && $html !== '' ? $html : null;
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
