<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Odeal\Message\CancelResponse;
use Omnipay\Odeal\Tests\TestCase;

class CancelTest extends TestCase
{
    public function test_request_payload_with_id()
    {
        $data = $this->createGateway()->void(['id' => 123456789])->getData();

        $this->assertSame(['id' => 123456789], $data);
    }

    public function test_request_payload_with_external_id()
    {
        $data = $this->createGateway()->void(['externalId' => 'ORDER-12345'])->getData();

        $this->assertSame(['externalId' => 'ORDER-12345'], $data);
    }

    public function test_id_wins_over_external_id()
    {
        $data = $this->createGateway()->void([
            'id' => 123456789,
            'externalId' => 'ORDER-12345',
        ])->getData();

        $this->assertSame(['id' => 123456789], $data);
    }

    public function test_external_id_falls_back_to_the_transaction_id()
    {
        $data = $this->createGateway()->void(['transactionId' => 'ORDER-99'])->getData();

        $this->assertSame(['externalId' => 'ORDER-99'], $data);
    }

    public function test_a_reference_is_required()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Either the id or the externalId (or transactionId) parameter is required.');

        $this->createGateway()->void()->getData();
    }

    public function test_success()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CancelSuccess.txt']);

        $response = $this->createGateway()->void(['id' => 123456789])->send();

        $this->assertInstanceOf(CancelResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('Islem iptal edildi', $response->getMessage());

        $this->assertSame('https://api-stg.odeal.com/vpos/cancel', (string) $this->getMockedRequests()[1]->getUri());
        $this->assertSame(['id' => 123456789], $this->sentRequestJson(1));
    }

    public function test_failure()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'CancelFailure.txt']);

        $response = $this->createGateway()->void(['id' => 123456789])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('Islem iptal edilemez', $response->getMessage());
    }
}
