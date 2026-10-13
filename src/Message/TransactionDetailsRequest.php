<?php

namespace Omnipay\Odeal\Message;

/**
 * POST /vpos/transaction-details - extended details of a transaction.
 *
 * Where check-status answers the bare status, this returns the amount, the
 * creation date and the full refund history. Exposed through the omnipay
 * standard fetchTransaction() as well.
 */
class TransactionDetailsRequest extends RemoteAbstractRequest
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
    public function sendData($data): TransactionDetailsResponse
    {
        $result = $this->sendJsonRequest($this->getGatewayEndpoint('transaction-details'), $data);

        $this->logOperationResult('transaction-details', $result, success: isset($result['result']['transactionDetail']));

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): TransactionDetailsResponse
    {
        return $this->response = new TransactionDetailsResponse($this, $data);
    }
}
