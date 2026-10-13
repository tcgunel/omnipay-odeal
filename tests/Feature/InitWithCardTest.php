<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Odeal\Message\InitWithCardResponse;
use Omnipay\Odeal\Tests\TestCase;

class InitWithCardTest extends TestCase
{
    public function test_request_payload_has_no_redirect_fields()
    {
        $data = $this->createGateway()->purchaseNon3D($this->validPurchaseOptions([
            'buyerName' => 'Test User',
            'successRedirectUrl' => 'https://kolaysiparis.test/ok',
            'failureRedirectUrl' => 'https://kolaysiparis.test/fail',
        ]))->getData();

        $this->assertSame(25.0, $data['amount']);
        $this->assertSame(1, $data['installment']);
        $this->assertSame('ORDER-12345', $data['externalId']);
        $this->assertArrayHasKey('card', $data);
        $this->assertArrayNotHasKey('returnUrl', $data);
        $this->assertArrayNotHasKey('buyer', $data);
        $this->assertArrayNotHasKey('successRedirectUrl', $data);
        $this->assertArrayNotHasKey('failureRedirectUrl', $data);
    }

    public function test_successful_direct_payment()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'InitWithCardSuccess.txt']);

        $response = $this->createGateway()->purchaseNon3D($this->validPurchaseOptions())->send();

        $this->assertInstanceOf(InitWithCardResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->getForce3DPayment());
        $this->assertSame('987654321', $response->getTransactionReference());
        $this->assertSame('Islem basarili', $response->getMessage());

        $this->assertSame('https://api-stg.odeal.com/vpos/init-with-card', (string) $this->getMockedRequests()[1]->getUri());
    }

    public function test_forced_3d_is_not_reported_as_success()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'InitWithCardForce3D.txt']);

        $response = $this->createGateway()->purchaseNon3D($this->validPurchaseOptions())->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->getForce3DPayment());
        $this->assertSame('3D dogrulama gereklidir', $response->getMessage());
        $this->assertSame('987654322', $response->getTransactionReference());
    }
}
