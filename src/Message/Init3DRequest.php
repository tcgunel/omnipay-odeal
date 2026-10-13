<?php

namespace Omnipay\Odeal\Message;

/**
 * POST /vpos/init-3d - start a 3D Secure payment.
 *
 * A successful call answers a self-contained 3D challenge page
 * (result.threeDFormHtml) that must be shown to the shopper, and Ödeal's
 * transaction id (result.id) which cancel/refund/status operate on. The
 * payment result itself is NOT in this response: after the shopper finishes
 * the 3D step, Ödeal posts the result to the returnUrl and the browser is
 * redirected to successRedirectUrl / failureRedirectUrl. The result can also
 * be confirmed deterministically with checkStatus(), which is what
 * completePurchase() does.
 */
class Init3DRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validateCredentials();

        $data = $this->basePaymentPayload();

        if ($this->hasValue($this->getReturnUrl())) {
            $data['returnUrl'] = (string) $this->getReturnUrl();
        }

        $buyer = $this->buyerPayload();

        if ($buyer !== []) {
            $data['buyer'] = $buyer;
        }

        if ($this->hasValue($this->getSuccessRedirectUrl())) {
            $data['successRedirectUrl'] = (string) $this->getSuccessRedirectUrl();
        }

        if ($this->hasValue($this->getFailureRedirectUrl())) {
            $data['failureRedirectUrl'] = (string) $this->getFailureRedirectUrl();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendData($data): Init3DResponse
    {
        $result = $this->sendJsonRequest($this->getGatewayEndpoint('init-3d'), $data);

        $this->logOperationResult('init-3d', $result);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): Init3DResponse
    {
        return $this->response = new Init3DResponse($this, $data);
    }
}
