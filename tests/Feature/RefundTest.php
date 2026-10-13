<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Odeal\Constants\ReasonType;
use Omnipay\Odeal\Message\RefundResponse;
use Omnipay\Odeal\Tests\TestCase;

class RefundTest extends TestCase
{
    public function test_request_payload()
    {
        $data = $this->createGateway()->refund([
            'externalId' => 'ORDER-12345',
            'amount' => '10.50',
            'reason' => 'Musteri talebi',
            'reasonType' => ReasonType::REQUESTED_BY_CUSTOMER,
        ])->getData();

        $this->assertSame('ORDER-12345', $data['externalId']);
        $this->assertSame(10.5, $data['amount']);
        $this->assertSame('Musteri talebi', $data['reason']);
        $this->assertSame('REQUESTEDBYCUSTOMER', $data['reasonType']);
    }

    public function test_reason_fields_are_optional()
    {
        $data = $this->createGateway()->refund([
            'id' => 123456789,
            'amount' => '5.00',
        ])->getData();

        $this->assertSame(['id' => 123456789, 'amount' => 5.0], $data);
    }

    public function test_amount_is_required()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->refund(['id' => 123456789])->getData();
    }

    public function test_a_reference_is_required()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->refund(['amount' => '5.00'])->getData();
    }

    public function test_an_unknown_reason_type_is_rejected()
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('reasonType');

        $this->createGateway()->refund([
            'id' => 123456789,
            'amount' => '5.00',
            'reasonType' => 'WHATEVER',
        ])->getData();
    }

    public function test_success()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'RefundSuccess.txt']);

        $response = $this->createGateway()->refund([
            'id' => 123456789,
            'amount' => '5.00',
        ])->send();

        $this->assertInstanceOf(RefundResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('Iade basarili', $response->getMessage());

        $this->assertSame('https://api-stg.odeal.com/vpos/refund', (string) $this->getMockedRequests()[1]->getUri());
    }

    public function test_failure()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'RefundFailure.txt']);

        $response = $this->createGateway()->refund([
            'id' => 123456789,
            'amount' => '999.00',
        ])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('Iade tutari islem tutarini asiyor', $response->getMessage());
    }
}
