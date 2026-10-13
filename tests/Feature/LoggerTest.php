<?php

namespace Omnipay\Odeal\Tests\Feature;

use GuzzleHttp\Psr7\Request;
use Http\Client\Exception\NetworkException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Odeal\Tests\Support\ArrayLogger;
use Omnipay\Odeal\Tests\TestCase;

class LoggerTest extends TestCase
{
    public function test_the_full_traffic_is_logged()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DSuccess.txt']);

        $response = $gateway->purchase($this->validPurchaseOptions())->send();

        $this->assertTrue($response->isRedirect());

        $this->assertTrue($logger->hasMessage('Odeal HTTP request: POST https://auth-sandbox.odeal.com/api/v1/token'));
        $this->assertTrue($logger->hasMessage('Odeal HTTP response: POST https://auth-sandbox.odeal.com/api/v1/token'));
        $this->assertTrue($logger->hasMessage('Odeal access token acquired.'));
        $this->assertTrue($logger->hasMessage('Odeal HTTP request: POST https://api-stg.odeal.com/vpos/init-3d'));
        $this->assertTrue($logger->hasMessage('Odeal HTTP response: POST https://api-stg.odeal.com/vpos/init-3d'));
        $this->assertTrue($logger->hasMessage('Odeal init-3d finished.'));

        $tokenRecord = $logger->findByMessage('Odeal HTTP request: POST https://auth-sandbox.odeal.com/api/v1/token');

        $this->assertNotNull($tokenRecord);
        $this->assertSame('debug', $tokenRecord['level']);
        $this->assertSame('test-client-id', $tokenRecord['context']['body']['clientId']);
        $this->assertSame('***', $tokenRecord['context']['body']['clientSecret']);

        $finished = $logger->findByMessage('Odeal init-3d finished.');

        $this->assertNotNull($finished);
        $this->assertSame('info', $finished['level']);
        $this->assertTrue($finished['context']['success']);
        $this->assertSame(123456789, $finished['context']['id']);
        $this->assertSame('ORDER-12345', $finished['context']['externalId']);
    }

    public function test_card_data_and_tokens_are_masked()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DSuccess.txt']);

        $gateway->purchase($this->validPurchaseOptions())->send();

        $record = $logger->findByMessage('Odeal HTTP request: POST https://api-stg.odeal.com/vpos/init-3d');

        $this->assertNotNull($record);
        $this->assertSame('411111******1111', $record['context']['body']['card']['cardNumber']);
        $this->assertSame('***', $record['context']['body']['card']['cvv']);
        $this->assertSame('***', $record['context']['headers']['Authorization']);

        $serialized = (string) json_encode($logger->records);

        // The raw card number, CVV, client secret and bearer token never
        // appear anywhere in the log.
        $this->assertStringNotContainsString('4111111111111111', $serialized);
        $this->assertStringNotContainsString('"737"', $serialized);
        $this->assertStringNotContainsString('test-client-secret', $serialized);
        $this->assertStringNotContainsString('test-token-1', $serialized);
    }

    public function test_failed_payment_is_logged_as_warning()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->setMockHttpResponse(['TokenSuccess.txt', 'Init3DFailure.txt']);

        $gateway->purchase($this->validPurchaseOptions())->send();

        $record = $logger->findByMessage('Odeal init-3d finished.');

        $this->assertNotNull($record);
        $this->assertSame('warning', $record['level']);
        $this->assertFalse($record['context']['success']);
        $this->assertSame('Kart bakiyesi yetersiz', $record['context']['message']);
    }

    public function test_transport_errors_are_logged_as_error()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->getMockClient()->addException(new NetworkException(
            'Connection timed out',
            new Request('POST', 'https://auth-sandbox.odeal.com/api/v1/token')
        ));

        try {
            $gateway->purchase($this->validPurchaseOptions())->send();
            $this->fail('An InvalidResponseException was expected.');
        } catch (InvalidResponseException $exception) {
            $this->assertStringContainsString('Odeal is unreachable: Connection timed out', $exception->getMessage());
        }

        $this->assertTrue($logger->hasMessage('Odeal transport error'));
        $this->assertContains('error', $logger->levels());
    }

    public function test_token_rotation_is_logged_as_warning()
    {
        $logger = new ArrayLogger();

        $gateway = $this->createGateway();
        $gateway->setLogger($logger);

        $this->setMockHttpResponse([
            'TokenSuccess.txt',
            'UnauthorizedToken.txt',
            'TokenSuccess2.txt',
            'Init3DSuccess.txt',
        ]);

        $gateway->purchase($this->validPurchaseOptions())->send();

        $record = $logger->findByMessage('Odeal rejected the access token; refreshing it and replaying the call.');

        $this->assertNotNull($record);
        $this->assertSame('warning', $record['level']);
    }
}
