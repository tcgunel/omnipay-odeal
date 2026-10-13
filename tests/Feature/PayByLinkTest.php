<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Odeal\Message\PayByLinkResponse;
use Omnipay\Odeal\Tests\TestCase;

class PayByLinkTest extends TestCase
{
    public function test_request_payload()
    {
        $data = $this->createGateway()->payByLink($this->validPurchaseOptions([
            'buyerName' => 'Test User',
            'buyerMail' => 'test@example.test',
        ]))->getData();

        $this->assertSame(25.0, $data['amount']);
        $this->assertSame(1, $data['installment']);
        $this->assertSame('ORDER-12345', $data['externalId']);
        $this->assertSame('https://kolaysiparis.test/odeal/return', $data['returnUrl']);
        $this->assertSame('Test User', $data['buyer']['buyerName']);
        $this->assertSame('test@example.test', $data['buyer']['buyerMail']);
        $this->assertArrayNotHasKey('card', $data);
    }

    public function test_success_returns_the_checkout_url()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PayByLinkSuccess.txt']);

        $response = $this->createGateway()->payByLink($this->validPurchaseOptions())->send();

        $this->assertInstanceOf(PayByLinkResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('https://checkout.odeal.com/link/abc123', $response->getCheckout3DUrl());
        $this->assertSame('555444333', $response->getTransactionReference());
        $this->assertSame('Link olusturuldu', $response->getMessage());

        $this->assertSame('https://api-stg.odeal.com/vpos/pay-by-link', (string) $this->getMockedRequests()[1]->getUri());
    }
}
