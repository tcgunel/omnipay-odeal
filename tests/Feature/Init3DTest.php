<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Common\Exception\InvalidCreditCardException;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Odeal\Message\Init3DResponse;
use Omnipay\Odeal\Tests\TestCase;

class Init3DTest extends TestCase
{
    public function test_request_payload()
    {
        $request = $this->createGateway()->purchase($this->validPurchaseOptions([
            'buyerName' => 'Test User',
            'buyerCity' => 'Istanbul',
            'buyerMail' => 'test@example.test',
            'buyerAddress' => 'Test Mah. 1. Sok.',
            'successRedirectUrl' => 'https://kolaysiparis.test/odeal/success',
            'failureRedirectUrl' => 'https://kolaysiparis.test/odeal/failure',
            'latitude' => 41.0082,
            'longitude' => 28.9784,
        ]));

        $data = $request->getData();

        $this->assertSame(25.0, $data['amount']);
        $this->assertSame(1, $data['installment']);
        $this->assertSame('TRY', $data['currency']);
        $this->assertSame('ORDER-12345', $data['externalId']);
        $this->assertSame('5551234567', $data['phone']);
        $this->assertSame('https://kolaysiparis.test/odeal/return', $data['returnUrl']);
        $this->assertSame('https://kolaysiparis.test/odeal/success', $data['successRedirectUrl']);
        $this->assertSame('https://kolaysiparis.test/odeal/failure', $data['failureRedirectUrl']);
        $this->assertSame(41.0082, $data['latitude']);
        $this->assertSame(28.9784, $data['longitude']);

        $this->assertSame([
            'buyerName' => 'Test User',
            'buyerCity' => 'Istanbul',
            'buyerMail' => 'test@example.test',
            'buyerAddress' => 'Test Mah. 1. Sok.',
        ], $data['buyer']);

        $this->assertSame('4111111111111111', $data['card']['cardNumber']);
        $this->assertSame('Test User', $data['card']['cardHolderName']);
        $this->assertSame('12', $data['card']['month']);
        $this->assertSame('30', $data['card']['year']);
        $this->assertSame('737', $data['card']['cvv']);
    }

    public function test_optional_fields_are_omitted()
    {
        $data = $this->createGateway()->purchase([
            'amount' => '10.50',
            'card' => $this->testCard(),
        ])->getData();

        $this->assertArrayNotHasKey('buyer', $data);
        $this->assertArrayNotHasKey('returnUrl', $data);
        $this->assertArrayNotHasKey('successRedirectUrl', $data);
        $this->assertArrayNotHasKey('failureRedirectUrl', $data);
        $this->assertArrayNotHasKey('externalId', $data);
        $this->assertArrayNotHasKey('phone', $data);
        $this->assertArrayNotHasKey('latitude', $data);
        $this->assertArrayNotHasKey('longitude', $data);
        $this->assertSame('TRY', $data['currency']);
        $this->assertSame(1, $data['installment']);
    }

    public function test_phone_is_normalised()
    {
        $data = $this->createGateway()->purchase($this->validPurchaseOptions([
            'phone' => '+90 555 123 45 67',
        ]))->getData();

        $this->assertSame('5551234567', $data['phone']);
    }

    public function test_external_id_overrides_the_transaction_id()
    {
        $data = $this->createGateway()->purchase($this->validPurchaseOptions([
            'externalId' => 'MUTABAKAT-1',
        ]))->getData();

        $this->assertSame('MUTABAKAT-1', $data['externalId']);
    }

    public function test_installment_is_passed_through()
    {
        $data = $this->createGateway()->purchase($this->validPurchaseOptions([
            'installment' => 6,
        ]))->getData();

        $this->assertSame(6, $data['installment']);
    }

    public function test_installment_above_twelve_is_rejected()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->purchase($this->validPurchaseOptions([
            'installment' => 15,
        ]))->getData();
    }

    public function test_amount_is_required()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->purchase([
            'card' => $this->testCard(),
        ])->getData();
    }

    public function test_a_card_is_required()
    {
        $this->expectException(InvalidRequestException::class);

        $this->createGateway()->purchase([
            'amount' => '10.00',
        ])->getData();
    }

    public function test_an_invalid_card_number_is_rejected()
    {
        $this->expectException(InvalidCreditCardException::class);

        $this->createGateway()->purchase($this->validPurchaseOptions([
            'card' => $this->testCard(['number' => '1234']),
        ]))->getData();
    }

    public function test_success_returns_the_challenge_page()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DSuccess.txt']);

        $response = $this->createGateway()->purchase($this->validPurchaseOptions())->send();

        $this->assertInstanceOf(Init3DResponse::class, $response);
        $this->assertTrue($response->isRedirect());
        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('3d-challenge', $response->getHtmlContent());
        $this->assertStringContainsString('3d-challenge', $response->getThreeDFormHtml());
        $this->assertSame('123456789', $response->getTransactionReference());
        $this->assertSame('3D form olusturuldu', $response->getMessage());

        // The payment went to the sandbox gateway with the bearer token.
        $request = $this->getMockedRequests()[1];

        $this->assertSame('https://api-stg.odeal.com/vpos/init-3d', (string) $request->getUri());
        $this->assertSame('Bearer test-token-1', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));

        // The body is the JSON payload. (json_encode writes whole floats
        // without a fraction - 25.0 becomes 25 - which is the same JSON number.)
        $this->assertEquals(25.0, $this->sentRequestJson(1)['amount']);
        $this->assertSame('ORDER-12345', $this->sentRequestJson(1)['externalId']);
    }

    public function test_failure_returns_a_failed_response()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DFailure.txt']);

        $response = $this->createGateway()->purchase($this->validPurchaseOptions())->send();

        $this->assertFalse($response->isRedirect());
        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getTransactionReference());
        $this->assertNull($response->getHtmlContent());
        $this->assertSame('Kart bakiyesi yetersiz', $response->getMessage());
    }

    public function test_production_endpoint_is_used_when_test_mode_is_off()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DSuccess.txt']);

        $this->createGateway(['testMode' => false])->purchase($this->validPurchaseOptions())->send();

        $this->assertSame('https://api.odeal.com/vpos/init-3d', (string) $this->getMockedRequests()[1]->getUri());
    }
}
