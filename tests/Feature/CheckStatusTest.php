<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Odeal\Constants\PaymentStatus;
use Omnipay\Odeal\Message\CheckStatusResponse;
use Omnipay\Odeal\Tests\TestCase;

class CheckStatusTest extends TestCase
{
    public function test_request_payload()
    {
        $data = $this->createGateway()->completePurchase(['externalId' => 'ORDER-12345'])->getData();

        $this->assertSame(['externalId' => 'ORDER-12345'], $data);

        $data = $this->createGateway()->checkStatus(['id' => '123456789'])->getData();

        $this->assertSame(['id' => '123456789'], $data);
    }

    public function test_external_id_falls_back_to_the_transaction_id()
    {
        $data = $this->createGateway()->completePurchase(['transactionId' => 'ORDER-77'])->getData();

        $this->assertSame(['externalId' => 'ORDER-77'], $data);
    }

    public function test_completed_payment()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CheckStatusCompleted.txt']);

        $response = $this->createGateway()->completePurchase(['externalId' => 'ORDER-12345'])->send();

        $this->assertInstanceOf(CheckStatusResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame(PaymentStatus::COMPLETED, $response->getPaymentStatus());
        $this->assertFalse($response->isPending());
        $this->assertFalse($response->isFailed());
        $this->assertSame('123456789', $response->getTransactionReference());
        $this->assertSame('REF123', $response->getReferenceId());
        $this->assertSame('AUTH01', $response->getAuthCode());
        $this->assertSame('00', $response->getResultCode());
        $this->assertSame('0062', $response->getBankCode());
        $this->assertSame('Test Vpos', $response->getVpos()['name']);
        $this->assertSame('COMPLETED', $response->getOrder()['status']);

        $this->assertSame('https://api-stg.odeal.com/vpos/check-status', (string) $this->getMockedRequests()[1]->getUri());
        $this->assertSame(['externalId' => 'ORDER-12345'], $this->sentRequestJson(1));
    }

    public function test_failed_payment()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CheckStatusFailed.txt']);

        $response = $this->createGateway()->completePurchase(['id' => '123456790'])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isFailed());
        $this->assertSame('51', $response->getResultCode());
    }

    public function test_not_found()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CheckStatusNotFound.txt']);

        $response = $this->createGateway()->completePurchase(['externalId' => 'ORDER-404'])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isNotFound());
        $this->assertNull($response->getTransactionReference());
    }

    public function test_partial_refund_status_is_normalised()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CheckStatusPartialRefund.txt']);

        $response = $this->createGateway()->completePurchase(['id' => '123456789'])->send();

        $this->assertSame(PaymentStatus::PARTIAL_REFUND, $response->getPaymentStatus());
        $this->assertTrue($response->isPartiallyRefunded());
        $this->assertFalse($response->isRefunded());
        $this->assertFalse($response->isSuccessful());
    }
}
