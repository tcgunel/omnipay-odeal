<?php

namespace Omnipay\Odeal\Message;

/**
 * POST /vpos/check-status - query the current status of a transaction.
 *
 * This is also the deterministic way to complete a 3D payment: the returnUrl
 * callback may be delayed or lost, and the callback payload itself is not
 * signed, so the result is confirmed against Ödeal by id/externalId. The
 * gateway exposes this through completePurchase() as well.
 */
class CheckStatusRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, int|string>
     */
    public function getData(): array
    {
        $this->validateCredentials();

        return $this->referencePayload();
    }

    /**
     * @param  array<string, int|string>  $data
     */
    public function sendData($data): CheckStatusResponse
    {
        $result = $this->sendJsonRequest($this->getGatewayEndpoint('check-status'), $data);

        $this->logOperationResult('check-status', $result);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): CheckStatusResponse
    {
        return $this->response = new CheckStatusResponse($this, $data);
    }
}
