<?php

namespace Omnipay\Odeal\Message;

use JsonException;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Odeal\Constants\Auth;
use Omnipay\Odeal\Constants\Endpoint;
use Omnipay\Odeal\Constants\PaymentStatus;
use Omnipay\Odeal\Helpers\Helper;
use Omnipay\Odeal\Traits\HasLogger;
use Omnipay\Odeal\Traits\HasParameters;
use Psr\Log\LogLevel;
use Throwable;

/**
 * JSON transport shared by every Ödeal request.
 *
 * Ödeal authenticates with an OAuth2-style bearer token:
 *
 *   1. POST {token base}/token with clientId/clientSecret answers an
 *      accessToken valid for ~30 minutes (expiresIn). There is no refresh
 *      token flow: a new token is fetched by repeating the call, and issuing
 *      a token invalidates the previous one.
 *   2. every /vpos call carries `Authorization: Bearer {accessToken}`.
 *
 * The token is reused for the lifetime of the request and can optionally be
 * shared across processes through any PSR-16 cache (setTokenCache()). When a
 * call still hits a 401 - a concurrent process rotated the token - the token
 * is fetched again and the call replayed once.
 *
 * Every request/response is logged through the injected PSR-3 logger with
 * the client secret, the bearer token and the card data masked.
 */
abstract class RemoteAbstractRequest extends AbstractRequest
{
    use HasLogger;
    use HasParameters;

    protected ?string $accessToken = null;

    /**
     * The keys of payload values that must never reach a log record.
     *
     * @var array<int, string>
     */
    private const SECRET_KEYS = [
        'authorization',
        'clientsecret',
        'accesstoken',
        'refreshtoken',
        'cvv',
        'cvc',
        'password',
    ];

    private const MASK = '***';

    abstract protected function createResponse(array $data);

    /**
     * Full URL of the token endpoint.
     */
    public function getTokenEndpoint(): string
    {
        $base = $this->getTokenUrl() ?: Endpoint::token((bool) $this->getTestMode());

        return rtrim($base, '/') . '/token';
    }

    /**
     * Full URL of a /vpos operation, e.g. "init-3d" or "check-status".
     */
    public function getGatewayEndpoint(string $path): string
    {
        $base = $this->getGatewayUrl() ?: Endpoint::gateway((bool) $this->getTestMode());

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    protected function validateCredentials(): void
    {
        $this->validate('clientId', 'clientSecret');
    }

    /**
     * True when a parameter carries a usable value (neither null nor '').
     */
    protected function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    /**
     * The client-credentials grant payload of POST /token.
     *
     * @return array<string, string>
     */
    protected function tokenPayload(): array
    {
        return [
            'clientId' => (string) $this->getClientId(),
            'clientSecret' => (string) $this->getClientSecret(),
            'grantType' => Auth::GRANT_TYPE,
            'scope' => Auth::SCOPE,
        ];
    }

    /**
     * POST /token - exchange the client credentials for a bearer token.
     *
     * @return array<string, mixed>
     */
    protected function requestToken(): array
    {
        return $this->sendJsonRequest($this->getTokenEndpoint(), $this->tokenPayload(), false);
    }

    /**
     * The bearer token for authenticated calls, reusing the in-memory and
     * (when configured) the cached token before asking Ödeal for a new one.
     *
     * @throws InvalidResponseException
     */
    protected function getAccessToken(bool $forceRefresh = false): string
    {
        if (! $forceRefresh) {
            if ($this->accessToken !== null) {
                return $this->accessToken;
            }

            $cached = $this->readCachedToken();

            if ($cached !== null) {
                return $this->accessToken = $cached;
            }
        }

        $data = $this->requestToken();

        $token = $data['accessToken'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new InvalidResponseException('Odeal token response did not contain an accessToken.');
        }

        $this->storeCachedToken($token, (int) ($data['expiresIn'] ?? 0));

        $this->getLogger()->info('Odeal access token acquired.', [
            'expiresIn' => $data['expiresIn'] ?? null,
        ]);

        return $this->accessToken = $token;
    }

    /**
     * The {id} or {externalId} reference that every transaction operation
     * identifies a payment by. Ödeal's own id wins when both are present;
     * externalId falls back to the omnipay transactionId.
     *
     * @return array<string, int|string>
     *
     * @throws InvalidRequestException
     */
    protected function referencePayload(bool $numericId = false): array
    {
        $id = $this->getId();

        if ($this->hasValue($id)) {
            return ['id' => $numericId ? (int) $id : (string) $id];
        }

        $externalId = $this->getExternalId() ?: $this->getTransactionId();

        if ($this->hasValue($externalId)) {
            return ['externalId' => (string) $externalId];
        }

        throw new InvalidRequestException(
            'Either the id or the externalId (or transactionId) parameter is required.'
        );
    }

    /**
     * The customer phone in Ödeal's 5XXXXXXXXX form, or null when absent.
     */
    protected function normalizedPhone(): ?string
    {
        $phone = $this->getPhone();

        if ($phone === null || $phone === '') {
            return null;
        }

        return Helper::normalizePhone($phone);
    }

    /**
     * The installment count. Ödeal accepts 1-12; 1 is the single-payment
     * value sent when nothing is set.
     *
     * @throws InvalidRequestException
     */
    protected function installmentValue(): int
    {
        $installment = (int) ($this->getInstallment() ?? 0);

        if ($installment <= 0) {
            $installment = 1;
        }

        if ($installment > 12) {
            throw new InvalidRequestException('The installment parameter must be between 1 and 12.');
        }

        return $installment;
    }

    /**
     * The payment fields shared by the payment endpoints.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidRequestException
     * @throws \Omnipay\Common\Exception\InvalidCreditCardException
     */
    protected function basePaymentPayload(bool $withCard = true): array
    {
        $this->validate('amount');

        $data = [
            'amount' => (float) $this->getAmount(),
            'installment' => $this->installmentValue(),
            'currency' => (string) ($this->getCurrency() ?: 'TRY'),
        ];

        if ($withCard) {
            $data['card'] = $this->cardPayload();
        }

        $externalId = $this->getExternalId() ?: $this->getTransactionId();

        if ($this->hasValue($externalId)) {
            $data['externalId'] = (string) $externalId;
        }

        $phone = $this->normalizedPhone();

        if ($phone !== null) {
            $data['phone'] = $phone;
        }

        if ($this->getLatitude() !== null) {
            $data['latitude'] = (float) $this->getLatitude();
        }

        if ($this->getLongitude() !== null) {
            $data['longitude'] = (float) $this->getLongitude();
        }

        return $data;
    }

    /**
     * The CardInfo model every payment endpoint expects. The CVV goes to the
     * gateway, but never into a log record (maskPayload()).
     *
     * @return array<string, string>
     *
     * @throws \Omnipay\Common\Exception\InvalidCreditCardException
     * @throws InvalidRequestException
     */
    protected function cardPayload(): array
    {
        $this->validate('card');

        $card = $this->getCard();

        $card->validate();

        $payload = [
            'cardNumber' => (string) $card->getNumber(),
            'month' => (string) $card->getExpiryMonth(),
            'year' => substr((string) $card->getExpiryYear(), -2),
            'cvv' => (string) $card->getCvv(),
        ];

        $holder = $card->getName();

        if ($this->hasValue($holder)) {
            $payload['cardHolderName'] = $holder;
        }

        return $payload;
    }

    /**
     * The optional BuyerInfo model.
     *
     * @return array<string, string>
     */
    protected function buyerPayload(): array
    {
        return array_filter([
            'buyerName' => $this->getBuyerName(),
            'buyerCity' => $this->getBuyerCity(),
            'buyerMail' => $this->getBuyerMail(),
            'buyerAddress' => $this->getBuyerAddress(),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * Send a JSON request and decode the JSON answer.
     *
     * Authenticated calls replay once after rotating the bearer token when
     * Ödeal answers 401.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws InvalidResponseException
     */
    protected function sendJsonRequest(string $url, array $payload, bool $authenticated = true): array
    {
        $logger = $this->getLogger();

        $tokenRetries = $authenticated ? 1 : 0;

        while (true) {
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            if ($this->hasValue($this->getAgent())) {
                $headers['X-ODEAL-AGENT'] = (string) $this->getAgent();
            }

            if ($authenticated) {
                $headers['Authorization'] = 'Bearer ' . $this->getAccessToken();
            }

            $logger->debug('Odeal HTTP request: POST ' . $url, [
                'headers' => $this->maskPayload($headers),
                'body' => $this->maskPayload($payload),
            ]);

            try {
                $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidResponseException('Odeal request body could not be encoded: ' . $exception->getMessage(), 0, $exception);
            }

            try {
                $httpResponse = $this->httpClient->request('POST', $url, $headers, $encoded);
            } catch (Throwable $exception) {
                $logger->error('Odeal transport error: POST ' . $url, ['exception' => $exception]);

                throw new InvalidResponseException('Odeal is unreachable: ' . $exception->getMessage(), 0, $exception);
            }

            $status = $httpResponse->getStatusCode();
            $body = (string) $httpResponse->getBody();

            $decoded = json_decode($body, true);

            $logger->debug('Odeal HTTP response: POST ' . $url, [
                'status' => $status,
                'body' => $this->maskPayload(is_array($decoded) ? $decoded : $body),
            ]);

            if ($status === 401) {
                if ($tokenRetries > 0) {
                    $tokenRetries--;

                    $logger->warning('Odeal rejected the access token; refreshing it and replaying the call.', [
                        'url' => $url,
                    ]);

                    $this->forgetCachedToken();

                    continue;
                }

                $message = is_array($decoded) ? $this->errorMessage($decoded) : null;

                throw new InvalidResponseException(
                    ($authenticated
                        ? 'Odeal rejected the access token (HTTP 401).'
                        : 'Odeal authentication failed: the clientId/clientSecret pair was rejected (HTTP 401).')
                    . ($message !== null ? ' ' . $message : '')
                );
            }

            if ($status < 200 || $status >= 300) {
                $message = is_array($decoded) ? $this->errorMessage($decoded) : null;

                throw new InvalidResponseException(
                    'Odeal request to ' . $url . ' failed with HTTP ' . $status . ($message !== null ? ': ' . $message : '.')
                );
            }

            if (! is_array($decoded)) {
                $logger->error('Odeal response could not be parsed as JSON: POST ' . $url, [
                    'status' => $status,
                    'body' => $body,
                ]);

                throw new InvalidResponseException('Odeal returned a malformed JSON response.');
            }

            return $decoded;
        }
    }

    /**
     * Log the business outcome of an operation on info/warning level.
     *
     * @param  array<string, mixed>  $data
     */
    protected function logOperationResult(string $operation, array $data, ?bool $success = null): void
    {
        if ($success === null) {
            $success = ($data['result']['success'] ?? null) === true
                || strtoupper((string) ($data['result']['payment_status'] ?? '')) === PaymentStatus::COMPLETED;
        }

        $this->getLogger()->log(
            $success ? LogLevel::INFO : LogLevel::WARNING,
            'Odeal ' . $operation . ' finished.',
            [
                'success' => $success,
                'message' => $data['result']['message'] ?? null,
                'id' => is_array($data['result'] ?? null) ? ($data['result']['id'] ?? null) : null,
                'paymentStatus' => $data['result']['payment_status'] ?? null,
                'externalId' => $this->getExternalId() ?: $this->getTransactionId(),
            ]
        );
    }

    /**
     * Best-effort extraction of a human-readable error message from an error body.
     *
     * @param  array<string, mixed>  $decoded
     */
    protected function errorMessage(array $decoded): ?string
    {
        foreach (['message', 'error_description', 'error', 'detail'] as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key]) && $decoded[$key] !== '') {
                return $decoded[$key];
            }
        }

        $result = $decoded['result'] ?? null;

        if (is_array($result) && isset($result['message']) && is_string($result['message']) && $result['message'] !== '') {
            return $result['message'];
        }

        return null;
    }

    /**
     * Mask credentials and card data before anything reaches the logger.
     */
    protected function maskPayload(mixed $payload, ?string $key = null): mixed
    {
        if (is_array($payload)) {
            $masked = [];

            foreach ($payload as $childKey => $value) {
                $masked[$childKey] = $this->maskPayload($value, is_string($childKey) ? $childKey : null);
            }

            return $masked;
        }

        if ($key === null) {
            return $payload;
        }

        $normalized = strtolower($key);

        if (in_array($normalized, self::SECRET_KEYS, true)) {
            return self::MASK;
        }

        if ($normalized === 'cardnumber') {
            return $this->maskCardNumber($payload);
        }

        return $payload;
    }

    /**
     * Keep the BIN and the last four digits of a card number for debugging,
     * mask everything in between.
     */
    protected function maskCardNumber(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === null || strlen($digits) < 10) {
            return self::MASK;
        }

        return substr($digits, 0, 6) . str_repeat('*', strlen($digits) - 10) . substr($digits, -4);
    }

    /**
     * The PSR-16 key the token is shared under. Keyed by the client id so
     * several merchants never overwrite each other's token.
     */
    protected function tokenCacheKey(): string
    {
        return $this->getTokenCachePrefix() . sha1((string) $this->getClientId());
    }

    protected function readCachedToken(): ?string
    {
        $cache = $this->getTokenCache();

        if ($cache === null) {
            return null;
        }

        try {
            $token = $cache->get($this->tokenCacheKey());
        } catch (Throwable $exception) {
            $this->getLogger()->warning('Odeal token cache read failed.', ['exception' => $exception]);

            return null;
        }

        return is_string($token) && $token !== '' ? $token : null;
    }

    protected function storeCachedToken(string $token, int $expiresIn): void
    {
        $cache = $this->getTokenCache();

        if ($cache === null) {
            return;
        }

        // Expire the cached copy slightly before Ödeal does, so a call that
        // starts just before the deadline never runs with a dead token.
        $ttl = max(1, $expiresIn - $this->getTokenCacheSkew());

        try {
            $cache->set($this->tokenCacheKey(), $token, $ttl);
        } catch (Throwable $exception) {
            $this->getLogger()->warning('Odeal token cache write failed.', ['exception' => $exception]);
        }
    }

    protected function forgetCachedToken(): void
    {
        $this->accessToken = null;

        $cache = $this->getTokenCache();

        if ($cache === null) {
            return;
        }

        try {
            $cache->delete($this->tokenCacheKey());
        } catch (Throwable $exception) {
            $this->getLogger()->warning('Odeal token cache delete failed.', ['exception' => $exception]);
        }
    }
}
