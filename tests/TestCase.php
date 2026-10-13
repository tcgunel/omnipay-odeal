<?php

namespace Omnipay\Odeal\Tests;

use Omnipay\Odeal\Gateway;
use Omnipay\Tests\GatewayTestCase;

class TestCase extends GatewayTestCase
{
    /** @var Gateway */
    public $gateway;

    public function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function createGateway(array $parameters = []): Gateway
    {
        $gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $gateway->initialize(array_merge($this->gatewayParameters(), $parameters));

        return $gateway;
    }

    /**
     * @return array<string, mixed>
     */
    protected function gatewayParameters(): array
    {
        return [
            'clientId' => 'test-client-id',
            'clientSecret' => 'test-client-secret',
            'testMode' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPurchaseOptions(array $overrides = []): array
    {
        return array_merge([
            'amount' => '25.00',
            'transactionId' => 'ORDER-12345',
            'card' => $this->testCard(),
            'installment' => 1,
            'phone' => '5551234567',
            'currency' => 'TRY',
            'returnUrl' => 'https://kolaysiparis.test/odeal/return',
        ], $overrides);
    }

    /**
     * A deterministic card so the log-masking assertions are stable.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>
     */
    protected function testCard(array $overrides = []): array
    {
        return array_merge([
            'firstName' => 'Test',
            'lastName' => 'User',
            'number' => '4111111111111111',
            'expiryMonth' => '12',
            'expiryYear' => '2030',
            'cvv' => '737',
        ], $overrides);
    }

    /**
     * The body of the n-th HTTP request sent through the mocked client.
     */
    protected function sentRequestBody(int $index): string
    {
        $requests = $this->getMockedRequests();

        $this->assertArrayHasKey($index, $requests);

        return (string) $requests[$index]->getBody();
    }

    /**
     * The decoded JSON body of the n-th HTTP request.
     *
     * @return array<string, mixed>
     */
    protected function sentRequestJson(int $index): array
    {
        $decoded = json_decode($this->sentRequestBody($index), true);

        $this->assertIsArray($decoded);

        return $decoded;
    }
}
