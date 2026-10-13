<?php

namespace Omnipay\Odeal\Message;

/**
 * POST /vpos/pay-by-link - create a hosted payment link.
 *
 * The card is entered on Ödeal's own form (checkout3DUrl), which then runs
 * the 3D verification. The payment result arrives on the returnUrl callback
 * exactly like a regular 3D payment.
 */
class PayByLinkRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validateCredentials();

        $data = $this->basePaymentPayload(false);

        if ($this->hasValue($this->getReturnUrl())) {
            $data['returnUrl'] = (string) $this->getReturnUrl();
        }

        $buyer = $this->buyerPayload();

        if ($buyer !== []) {
            $data['buyer'] = $buyer;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendData($data): PayByLinkResponse
    {
        $result = $this->sendJsonRequest($this->getGatewayEndpoint('pay-by-link'), $data);

        $this->logOperationResult('pay-by-link', $result);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): PayByLinkResponse
    {
        return $this->response = new PayByLinkResponse($this, $data);
    }
}
