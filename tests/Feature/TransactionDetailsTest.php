<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Odeal\Message\TransactionDetailsResponse;
use Omnipay\Odeal\Tests\TestCase;

class TransactionDetailsTest extends TestCase
{
    public function test_success()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'TransactionDetailsSuccess.txt']);

        $response = $this->createGateway()->fetchTransaction(['externalId' => 'ORDER-12345'])->send();

        $this->assertInstanceOf(TransactionDetailsResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('123456789', $response->getTransactionReference());
        $this->assertSame('ORDER-12345', $response->getExternalId());
        $this->assertSame('COMPLETED', $response->getStatus());
        $this->assertSame(25.0, $response->getAmount());
        $this->assertSame(5.0, $response->getRefundedAmount());
        $this->assertSame(1699999999000, $response->getCreatedDate());
        $this->assertSame('2023-11-14 22:13:19', $response->getCreatedDateFormatted());

        $refunds = $response->getRefunds();

        $this->assertCount(1, $refunds);
        $this->assertSame(5.0, $refunds[0]['amount']);
        $this->assertSame('REQUESTEDBYCUSTOMER', $refunds[0]['reasonType']);

        $this->assertSame('https://api-stg.odeal.com/vpos/transaction-details', (string) $this->getMockedRequests()[1]->getUri());
        $this->assertSame(['externalId' => 'ORDER-12345'], $this->sentRequestJson(1));
    }
}
