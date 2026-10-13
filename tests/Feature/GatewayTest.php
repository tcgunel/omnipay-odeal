<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Odeal\Gateway;
use Omnipay\Odeal\Message\CancelRequest;
use Omnipay\Odeal\Message\CheckStatusRequest;
use Omnipay\Odeal\Message\Init3DRequest;
use Omnipay\Odeal\Message\InitWithCardRequest;
use Omnipay\Odeal\Message\PayByLinkRequest;
use Omnipay\Odeal\Message\RefundRequest;
use Omnipay\Odeal\Message\TokenRequest;
use Omnipay\Odeal\Message\TransactionDetailsRequest;
use Omnipay\Odeal\Tests\TestCase;
use Psr\Log\NullLogger;

class GatewayTest extends TestCase
{
    public function test_name_and_default_parameters()
    {
        $gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $gateway->initialize();

        $this->assertSame('Odeal', $gateway->getName());
        $this->assertSame('', $gateway->getClientId());
        $this->assertSame('', $gateway->getClientSecret());
        $this->assertNull($gateway->getAgent());
        $this->assertNull($gateway->getTokenUrl());
        $this->assertNull($gateway->getGatewayUrl());
        $this->assertTrue($gateway->getSecure());
        $this->assertTrue($gateway->getTestMode());
        $this->assertSame('odeal.token.', $gateway->getTokenCachePrefix());
        $this->assertSame(30, $gateway->getTokenCacheSkew());
    }

    public function test_default_logger_is_a_null_logger()
    {
        $this->assertInstanceOf(NullLogger::class, $this->gateway->getLogger());
    }

    public function test_purchase_defaults_to_the_3d_flow()
    {
        $this->assertInstanceOf(Init3DRequest::class, $this->createGateway()->purchase());
    }

    public function test_purchase_honours_the_secure_option()
    {
        $this->assertInstanceOf(InitWithCardRequest::class, $this->createGateway()->purchase(['secure' => false]));
    }

    public function test_purchase_honours_the_secure_gateway_parameter()
    {
        $gateway = $this->createGateway(['secure' => false]);

        $this->assertInstanceOf(InitWithCardRequest::class, $gateway->purchase());
    }

    public function test_purchase_non_3d_always_creates_the_direct_flow()
    {
        $this->assertInstanceOf(InitWithCardRequest::class, $this->createGateway()->purchaseNon3D());
    }

    public function test_request_classes_of_every_operation()
    {
        $gateway = $this->createGateway();

        $this->assertInstanceOf(CheckStatusRequest::class, $gateway->completePurchase());
        $this->assertInstanceOf(CheckStatusRequest::class, $gateway->checkStatus());
        $this->assertInstanceOf(TransactionDetailsRequest::class, $gateway->fetchTransaction());
        $this->assertInstanceOf(RefundRequest::class, $gateway->refund());
        $this->assertInstanceOf(CancelRequest::class, $gateway->void());
        $this->assertInstanceOf(PayByLinkRequest::class, $gateway->payByLink());
        $this->assertInstanceOf(TokenRequest::class, $gateway->fetchToken());
    }

    public function test_gateway_parameters_reach_the_request()
    {
        $request = $this->createGateway(['installment' => 6])->purchase();

        $this->assertSame(6, $request->getInstallment());
        $this->assertSame('test-client-id', $request->getClientId());
        $this->assertSame('test-client-secret', $request->getClientSecret());
    }
}
