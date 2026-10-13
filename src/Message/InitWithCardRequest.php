<?php

namespace Omnipay\Odeal\Message;

/**
 * POST /vpos/init-with-card - direct (non-3D) payment.
 *
 * The result is synchronous. A bank may still force the 3D flow for a card
 * or amount; the response then carries result.force3DPayment = true and the
 * payment must be restarted through Init3DRequest.
 */
class InitWithCardRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validateCredentials();

        return $this->basePaymentPayload();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendData($data): InitWithCardResponse
    {
        $result = $this->sendJsonRequest($this->getGatewayEndpoint('init-with-card'), $data);

        $this->logOperationResult('init-with-card', $result);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): InitWithCardResponse
    {
        return $this->response = new InitWithCardResponse($this, $data);
    }
}
