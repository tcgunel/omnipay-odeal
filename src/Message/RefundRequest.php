<?php

namespace Omnipay\Odeal\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Odeal\Constants\ReasonType;

/**
 * POST /vpos/refund - refund a transaction after day-end.
 *
 * Full or partial; several partial refunds are allowed as long as their sum
 * does not exceed the original amount. Identified by Ödeal's id or by the
 * externalId the payment was created with.
 */
class RefundRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, int|float|string>
     *
     * @throws InvalidRequestException
     */
    public function getData(): array
    {
        $this->validateCredentials();
        $this->validate('amount');

        if (! ReasonType::isValid($this->getReasonType() !== null ? (string) $this->getReasonType() : null)) {
            throw new InvalidRequestException(
                'The reasonType parameter must be one of: ' . implode(', ', ReasonType::all()) . '.'
            );
        }

        $data = $this->referencePayload(numericId: true);

        $data['amount'] = (float) $this->getAmount();

        if ($this->hasValue($this->getReason())) {
            $data['reason'] = (string) $this->getReason();
        }

        if ($this->hasValue($this->getReasonType())) {
            $data['reasonType'] = (string) $this->getReasonType();
        }

        return $data;
    }

    /**
     * @param  array<string, int|float|string>  $data
     */
    public function sendData($data): RefundResponse
    {
        $result = $this->sendJsonRequest($this->getGatewayEndpoint('refund'), $data);

        $this->logOperationResult('refund', $result);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): RefundResponse
    {
        return $this->response = new RefundResponse($this, $data);
    }
}
