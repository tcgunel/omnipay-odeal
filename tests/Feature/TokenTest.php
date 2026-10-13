<?php

namespace Omnipay\Odeal\Tests\Feature;

use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Odeal\Message\TokenResponse;
use Omnipay\Odeal\Tests\Support\ArrayCache;
use Omnipay\Odeal\Tests\TestCase;

class TokenTest extends TestCase
{
    public function test_token_request_payload_and_endpoint()
    {
        $this->setMockHttpResponse('TokenSuccess.txt');

        $response = $this->createGateway()->fetchToken()->send();

        $this->assertInstanceOf(TokenResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('test-token-1', $response->getAccessToken());
        $this->assertSame(1799, $response->getExpiresIn());
        $this->assertSame('bearer', $response->getTokenType());
        $this->assertSame('vpos', $response->getScope());

        $body = $this->sentRequestJson(0);

        $this->assertSame('test-client-id', $body['clientId']);
        $this->assertSame('test-client-secret', $body['clientSecret']);
        $this->assertSame('client_credentials', $body['grantType']);
        $this->assertSame('vpos', $body['scope']);

        $request = $this->getMockedRequests()[0];

        $this->assertSame('https://auth-sandbox.odeal.com/api/v1/token', (string) $request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('', $request->getHeaderLine('Authorization'));
    }

    public function test_token_endpoint_uses_production_when_test_mode_is_off()
    {
        $this->setMockHttpResponse('TokenSuccess.txt');

        $gateway = $this->createGateway(['testMode' => false]);
        $gateway->fetchToken()->send();

        $this->assertSame('https://auth.odeal.com/api/v1/token', (string) $this->getMockedRequests()[0]->getUri());
    }

    public function test_bad_credentials_throw()
    {
        $this->setMockHttpResponse('TokenBadCredentials.txt');

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Client authentication failed');

        $this->createGateway()->fetchToken()->send();
    }

    public function test_purchase_fetches_the_token_lazily()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DSuccess.txt']);

        $response = $this->createGateway()->purchase($this->validPurchaseOptions())->send();

        $this->assertTrue($response->isRedirect());

        // Token call + payment call.
        $this->assertCount(2, $this->getMockedRequests());

        // The payment call carries the freshly fetched bearer token.
        $this->assertSame('Bearer test-token-1', $this->getMockedRequests()[1]->getHeaderLine('Authorization'));
    }

    public function test_token_is_reused_from_the_psr16_cache()
    {
        $cache = new ArrayCache();
        $cache->set('odeal.token.' . sha1('test-client-id'), 'cached-token', 1200);

        $this->setMockHttpResponse('Init3DSuccess.txt');

        $gateway = $this->createGateway();
        $gateway->setTokenCache($cache);

        $response = $gateway->purchase($this->validPurchaseOptions())->send();

        $this->assertTrue($response->isRedirect());

        // Only the payment call went out; the token came from the cache.
        $this->assertCount(1, $this->getMockedRequests());
        $this->assertSame('Bearer cached-token', $this->getMockedRequests()[0]->getHeaderLine('Authorization'));
    }

    public function test_fetched_token_is_stored_in_the_cache_with_the_skew()
    {
        $cache = new ArrayCache();

        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DSuccess.txt']);

        $gateway = $this->createGateway();
        $gateway->setTokenCache($cache);

        $gateway->purchase($this->validPurchaseOptions())->send();

        $key = 'odeal.token.' . sha1('test-client-id');

        $this->assertSame('test-token-1', $cache->get($key));
        $this->assertSame(1799 - 30, $cache->ttls[$key]);
    }

    public function test_401_replays_the_call_after_refreshing_the_token()
    {
        $this->setMockHttpResponse([
            'TokenSuccess.txt',
            'UnauthorizedToken.txt',
            'TokenSuccess2.txt',
            'Init3DSuccess.txt',
        ]);

        $response = $this->createGateway()->purchase($this->validPurchaseOptions())->send();

        $this->assertTrue($response->isRedirect());
        $this->assertCount(4, $this->getMockedRequests());

        // The replay carries the rotated token.
        $this->assertSame('Bearer test-token-2', $this->getMockedRequests()[3]->getHeaderLine('Authorization'));
    }

    public function test_401_after_the_retry_throws()
    {
        $this->setMockHttpResponse([
            'TokenSuccess.txt',
            'UnauthorizedToken.txt',
            'TokenSuccess2.txt',
            'UnauthorizedToken.txt',
        ]);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Odeal rejected the access token (HTTP 401).');

        $this->createGateway()->purchase($this->validPurchaseOptions())->send();
    }

    public function test_custom_token_and_gateway_urls_are_honoured()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DSuccess.txt']);

        $gateway = $this->createGateway([
            'tokenUrl' => 'https://proxy.test/auth/v1',
            'gatewayUrl' => 'https://proxy.test/gw/',
        ]);

        $gateway->purchase($this->validPurchaseOptions())->send();

        $this->assertSame('https://proxy.test/auth/v1/token', (string) $this->getMockedRequests()[0]->getUri());
        $this->assertSame('https://proxy.test/gw/init-3d', (string) $this->getMockedRequests()[1]->getUri());
    }

    public function test_the_agent_header_is_sent_when_configured()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DSuccess.txt']);

        $gateway = $this->createGateway(['agent' => 'KOLAYSIPARIS']);
        $gateway->purchase($this->validPurchaseOptions())->send();

        $this->assertSame('KOLAYSIPARIS', $this->getMockedRequests()[0]->getHeaderLine('X-ODEAL-AGENT'));
        $this->assertSame('KOLAYSIPARIS', $this->getMockedRequests()[1]->getHeaderLine('X-ODEAL-AGENT'));
    }

    public function test_server_errors_are_reported_with_the_gateway_message()
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'ServerError.txt']);

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('HTTP 500: Beklenmeyen bir hata olustu');

        $this->createGateway()->purchase($this->validPurchaseOptions())->send();
    }

    public function test_malformed_json_is_rejected()
    {
        $this->setMockHttpResponse('MalformedJson.txt');

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('malformed JSON');

        $this->createGateway()->fetchToken()->send();
    }
}
