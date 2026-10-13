<?php

namespace Omnipay\Odeal\Message;

/**
 * POST /vpos/cancel - cancel (iptal) a transaction before day-end.
 *
 * The money never leaves the shopper's card. Only a full cancellation is
 * possible; a partial reversal is a refund. Identified by Ödeal's id or by
 * the externalId the payment was created with.
 */
class CancelRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, int|string>
     */
    public function getData(): array
    {
        $this->validateCredentials();

        return $this->referencePayload(numericId: true);
    }

    /**
     * @param  array<string, int|string>  $data
     */
    public function sendData($data): CancelResponse
    {
        $result = $this->sendJsonRequest($this->getGatewayEndpoint('cancel'), $data);

        $this->logOperationResult('cancel', $result);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): CancelResponse
    {
        return $this->response = new CancelResponse($this, $data);
    }
}
