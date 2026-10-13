<?php

namespace Omnipay\Odeal\Message;

/**
 * Response of POST /vpos/transaction-details.
 */
class TransactionDetailsResponse extends RemoteAbstractResponse
{
    public function isSuccessful(): bool
    {
        return $this->getTransactionDetail() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTransactionDetail(): ?array
    {
        $detail = $this->value('result', 'transactionDetail');

        return is_array($detail) ? $detail : null;
    }

    public function getTransactionReference(): ?string
    {
        $id = $this->value('result', 'transactionDetail', 'id');

        return $id === null ? null : (string) $id;
    }

    public function getExternalId(): ?string
    {
        $externalId = $this->value('result', 'transactionDetail', 'externalId');

        return $externalId === null ? null : (string) $externalId;
    }

    public function getStatus(): ?string
    {
        $status = $this->value('result', 'transactionDetail', 'status');

        return $status === null ? null : (string) $status;
    }

    public function getAmount(): ?float
    {
        $amount = $this->value('result', 'transactionDetail', 'amount');

        return is_numeric($amount) ? (float) $amount : null;
    }

    public function getRefundedAmount(): ?float
    {
        $amount = $this->value('result', 'transactionDetail', 'refundedAmount');

        return is_numeric($amount) ? (float) $amount : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRefunds(): array
    {
        $refunds = $this->value('result', 'transactionDetail', 'refunds');

        return is_array($refunds) ? $refunds : [];
    }

    /**
     * Creation time as a unix timestamp in milliseconds.
     */
    public function getCreatedDate(): ?int
    {
        $created = $this->value('result', 'transactionDetail', 'createdDate');

        return is_numeric($created) ? (int) $created : null;
    }

    public function getCreatedDateFormatted(): ?string
    {
        $created = $this->value('result', 'transactionDetail', 'createdDateFormatted');

        return $created === null ? null : (string) $created;
    }

    public function getMessage(): ?string
    {
        return $this->resultMessage();
    }
}
