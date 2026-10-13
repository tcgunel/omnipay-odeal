<?php

namespace Omnipay\Odeal;

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Http\ClientInterface;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Odeal\Message\CancelRequest;
use Omnipay\Odeal\Message\CheckStatusRequest;
use Omnipay\Odeal\Message\Init3DRequest;
use Omnipay\Odeal\Message\InitWithCardRequest;
use Omnipay\Odeal\Message\PayByLinkRequest;
use Omnipay\Odeal\Message\RefundRequest;
use Omnipay\Odeal\Message\RemoteAbstractRequest;
use Omnipay\Odeal\Message\TokenRequest;
use Omnipay\Odeal\Message\TransactionDetailsRequest;
use Omnipay\Odeal\Traits\HasLogger;
use Omnipay\Odeal\Traits\HasParameters;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * Ödeal Gateway
 *
 * Implements the Ödeal Virtual POS API (docs.odeal.com/sanalpos):
 * 3D (init-3d) and direct non-3D (init-with-card) payments, pay-by-link,
 * cancel, refund and the transaction query endpoints.
 *
 * Ödeal authenticates with a short-lived bearer token fetched from the
 * clientId/clientSecret pair; the gateway does that lazily inside every
 * operation. An optional PSR-3 logger can be injected via the constructor or
 * setLogger(); it receives every request/response with credentials and card
 * data masked.
 *
 * (c) Tolga Can Günel
 * http://www.github.com/tcgunel/omnipay-odeal
 *
 * @method \Omnipay\Common\Message\NotificationInterface acceptNotification(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface authorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface completeAuthorize(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface createCard(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface updateCard(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface deleteCard(array $options = [])
 */
class Gateway extends AbstractGateway
{
    use HasLogger;
    use HasParameters;

    public function __construct(
        ?ClientInterface $httpClient = null,
        ?HttpRequest $httpRequest = null,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger;

        parent::__construct($httpClient, $httpRequest);
    }

    public function getName(): string
    {
        return 'Odeal';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return [
            'clientId' => '',
            'clientSecret' => '',
            'agent' => null,
            'tokenUrl' => null,
            'gatewayUrl' => null,
            'secure' => true,
            'testMode' => true,
        ];
    }

    /**
     * Start a payment. The 3D flow (init-3d) is the default and the most
     * secure option; pass `secure => false` for a direct non-3D charge.
     *
     * @param  array<string, mixed>  $options
     */
    public function purchase(array $options = []): AbstractRequest
    {
        $secure = (bool) ($options['secure'] ?? $this->getSecure() ?? true);

        return $this->createRequest($secure ? Init3DRequest::class : InitWithCardRequest::class, $options);
    }

    /**
     * Direct (non-3D) payment. The issuer may still force a 3D verification;
     * the response then carries getForce3DPayment() = true.
     *
     * @param  array<string, mixed>  $options
     */
    public function purchaseNon3D(array $options = []): AbstractRequest
    {
        return $this->createRequest(InitWithCardRequest::class, array_merge($options, ['secure' => false]));
    }

    /**
     * Confirm the result of a 3D payment through the status API. Call it with
     * the id or externalId carried by the returnUrl callback:
     *
     *   $response = $gateway->completePurchase(['externalId' => $orderId])->send();
     *
     * @param  array<string, mixed>  $options
     */
    public function completePurchase(array $options = []): AbstractRequest
    {
        return $this->createRequest(CheckStatusRequest::class, $options);
    }

    /**
     * Query the current status of a transaction (by id or externalId).
     *
     * @param  array<string, mixed>  $options
     */
    public function checkStatus(array $options = []): AbstractRequest
    {
        return $this->createRequest(CheckStatusRequest::class, $options);
    }

    /**
     * Extended transaction details incl. the refund history.
     *
     * @param  array<string, mixed>  $options
     */
    public function fetchTransaction(array $options = []): AbstractRequest
    {
        return $this->createRequest(TransactionDetailsRequest::class, $options);
    }

    /**
     * Refund a transaction (full or partial).
     *
     * @param  array<string, mixed>  $options
     */
    public function refund(array $options = []): AbstractRequest
    {
        return $this->createRequest(RefundRequest::class, $options);
    }

    /**
     * Cancel (iptal) a transaction before day-end. Full amount only; partial
     * reversals must go through refund().
     *
     * @param  array<string, mixed>  $options
     */
    public function void(array $options = []): AbstractRequest
    {
        return $this->createRequest(CancelRequest::class, $options);
    }

    /**
     * Create a hosted payment link (pay-by-link); the response carries the
     * checkout3DUrl the shopper is sent to.
     *
     * @param  array<string, mixed>  $options
     */
    public function payByLink(array $options = []): AbstractRequest
    {
        return $this->createRequest(PayByLinkRequest::class, $options);
    }

    /**
     * Fetch a fresh bearer token (credential checks, e.g. a "test connection"
     * button in a shop panel). Operations fetch their token automatically.
     *
     * @param  array<string, mixed>  $options
     */
    public function fetchToken(array $options = []): AbstractRequest
    {
        return $this->createRequest(TokenRequest::class, $options);
    }

    /**
     * Forward the injected logger to every request the gateway creates.
     *
     * @param  string  $class
     * @param  array<string, mixed>  $parameters
     */
    protected function createRequest($class, array $parameters)
    {
        /** @var RemoteAbstractRequest $request */
        $request = parent::createRequest($class, $parameters);

        $request->setLogger($this->logger);

        return $request;
    }
}
