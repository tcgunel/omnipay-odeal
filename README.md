# Omnipay: Ödeal

**Ödeal Virtual POS gateway for the Omnipay PHP payment processing library**

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway payment processing library for PHP.
This package implements the Ödeal (odeal.com) virtual POS API — 3D and non-3D card payments, pay-by-link,
cancel (iptal), refund and the transaction query endpoints.

Documentation: [docs.odeal.com/sanalpos](https://docs.odeal.com/sanalpos/tr/)

## How it works

Ödeal authenticates with a short-lived bearer token:

1. `POST /token` on the **token base URL** with `clientId` / `clientSecret` answers an `accessToken`
   (valid ~30 minutes, `expiresIn`). There is no refresh token flow; issuing a new token invalidates the previous one.
2. Every `/vpos` operation on the **gateway base URL** carries `Authorization: Bearer {accessToken}`.

The gateway fetches the token lazily inside every operation, reuses it for the lifetime of the request and can share
it across processes through any PSR-16 cache. When a call still hits a `401` — a concurrent process rotated the token —
the token is fetched again and the call replayed once.

| Environment | Token base URL                      | Gateway base URL                |
|-------------|-------------------------------------|---------------------------------|
| Test        | `https://auth-sandbox.odeal.com/api/v1/` | `https://api-stg.odeal.com/vpos/` |
| Production  | `https://auth.odeal.com/api/v1/`    | `https://api.odeal.com/vpos/`     |

## Requirements

- PHP 8.3+
- A `clientId` / `clientSecret` pair per environment (shared by Ödeal during onboarding)
- For the 3D flow: an endpoint that receives Ödeal's `returnUrl` callback

## Installation

```bash
composer require tcgunel/omnipay-odeal
```

## Usage

### Gateway initialization

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('Odeal');

$gateway->setClientId('your-client-id');
$gateway->setClientSecret('your-client-secret');
$gateway->setTestMode(true); // false for production
```

Optional:

```php
$gateway->setAgent('KOLAYSIPARIS');           // sent as the X-ODEAL-AGENT header
$gateway->setTokenUrl('https://proxy.test/auth/v1');  // override the token base URL
$gateway->setGatewayUrl('https://proxy.test/gw');     // override the gateway base URL
```

### Logger (PSR-3, optional)

The package never writes to files or the error log on its own. Inject any PSR-3 logger and the gateway forwards it to
every request. Every request/response is logged on `debug` level, operation outcomes on `info`/`warning` and transport
problems on `error`. Credentials and card data are masked before anything reaches the logger:

- `clientSecret`, bearer tokens and CVVs are replaced with `***`
- card numbers keep their BIN and last four digits only (`411111******1111`)

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$gateway->setLogger(new Logger('odeal', [new StreamHandler('php://stderr')]));
```

With Laravel:

```php
$gateway->setLogger(\Illuminate\Support\Facades\Log::channel('odeal'));
```

Without a logger a `Psr\Log\NullLogger` is used and nothing is logged.

### Shared token cache (PSR-16, optional)

Because issuing a token invalidates the previous one, a shared cache avoids needless token churn when several
requests run in parallel. Any PSR-16 cache is accepted; the key is derived from the client id, so several merchants
never overwrite each other's token:

```php
$gateway->setTokenCache($psr16Cache); // e.g. Laravel: cache()->store()
```

The cached token expires `tokenCacheSkew` seconds (default 30) before Ödeal expires it. A token rejected with `401`
is deleted from the cache and replaced.

### 3D payment (recommended)

`purchase()` starts the 3D flow by default:

```php
$response = $gateway->purchase([
    'amount'        => '25.00',
    'transactionId' => 'ORDER-12345', // becomes externalId unless passed explicitly
    'card'          => [
        'number'      => '4026719999999999',
        'expiryMonth' => '12',
        'expiryYear'  => '2030',
        'cvv'         => '123',
        'firstName'   => 'Test',
        'lastName'    => 'User',
    ],
    'installment'   => 1,             // 1-12, 1 = single payment
    'currency'      => 'TRY',         // optional, TRY is the default
    'phone'         => '5551234567',  // 5XXXXXXXXX; 05xx / +90 5xx are normalised
    'returnUrl'     => 'https://shop.test/odeal/return', // receives Ödeal's server-to-server callback
    'successRedirectUrl' => 'https://shop.test/order/ok',
    'failureRedirectUrl' => 'https://shop.test/order/fail',
    'buyerName'     => 'Test User',   // optional BuyerInfo fields
    'buyerCity'     => 'Istanbul',
    'buyerMail'     => 'test@example.test',
    'buyerAddress'  => 'Test Mah. 1. Sok.',
])->send();

if ($response->isRedirect()) {
    // Show the self-contained 3D challenge page to the shopper.
    echo $response->getHtmlContent();

    // Save Ödeal's transaction id NOW: cancel/refund/status operate on it.
    $transactionId = $response->getTransactionReference();
} else {
    echo 'Payment could not be started: ' . $response->getMessage();
}
```

A successful init means *the challenge page was issued*, not that the payment is complete — that is why
`isRedirect()` is true and `isSuccessful()` is false while the 3D step is outstanding. Ödeal posts the result to the
`returnUrl` and redirects the browser to `successRedirectUrl` / `failureRedirectUrl` afterwards.

### Completing / verifying a 3D payment

The callback payload is not signed and may be delayed or lost, so confirm the result against Ödeal by `id` or
`externalId` (`completePurchase()` is a check-status call):

```php
$response = $gateway->completePurchase([
    'externalId' => 'ORDER-12345', // or 'id' => $transactionId
])->send();

if ($response->isSuccessful()) {       // payment_status === COMPLETED
    echo 'Paid. Auth code: ' . $response->getAuthCode();
} elseif ($response->isPending()) {
    // still processing — poll again shortly
} else {
    echo 'Payment status: ' . $response->getPaymentStatus();
}
```

`checkStatus()` does the same; it is exposed separately for reconciliation jobs.

### Non-3D (direct) payment

```php
$response = $gateway->purchaseNon3D([
    'amount' => '25.00',
    'card'   => [/* ... */],
])->send();

// Or: $gateway->purchase([... 'secure' => false ...])
// Or set the gateway default: $gateway->setSecure(false);

if ($response->isSuccessful()) {
    echo 'Paid. Transaction id: ' . $response->getTransactionReference();
} elseif ($response->getForce3DPayment()) {
    // The issuer demands 3D for this card/amount: restart through purchase().
}
```

### Pay by link

```php
$response = $gateway->payByLink([
    'amount'    => '25.00',
    'returnUrl' => 'https://shop.test/odeal/return',
])->send();

if ($response->isSuccessful()) {
    redirect($response->getCheckout3DUrl());
}
```

### Cancel (iptal — before day-end)

```php
$response = $gateway->void([
    'id' => 123456789, // or 'externalId' => 'ORDER-12345'
])->send();

if ($response->isSuccessful()) {
    echo 'Cancelled.';
}
```

Only the full amount can be cancelled; a partial reversal must be a refund.

### Refund (full or partial)

```php
$response = $gateway->refund([
    'id'         => 123456789,          // or 'externalId' => 'ORDER-12345'
    'amount'     => '10.50',
    'reason'     => 'Musteri talebi',   // optional
    'reasonType' => 'REQUESTEDBYCUSTOMER', // DUPLICATE, FRAUDULENT, REQUESTEDBYCUSTOMER
])->send();
```

Several partial refunds are allowed as long as their sum does not exceed the original amount.

### Transaction details

```php
$response = $gateway->fetchTransaction(['externalId' => 'ORDER-12345'])->send();

$response->getStatus();            // COMPLETED, REFUND, ...
$response->getAmount();            // 25.00
$response->getRefundedAmount();    // 5.00
$response->getRefunds();           // refund history
$response->getCreatedDateFormatted();
```

### Credential check

`fetchToken()` deliberately requests a new bearer token — useful for a "test connection" button. It throws an
`InvalidResponseException` when Ödeal rejects the `clientId`/`clientSecret` pair:

```php
try {
    $gateway->fetchToken()->send();
} catch (\Omnipay\Common\Exception\InvalidResponseException $e) {
    echo 'Credentials rejected: ' . $e->getMessage();
}
```

## Error handling

- Business failures (HTTP 200, `result.success = false`) come back as a normal response — check
  `isSuccessful()` and `getMessage()`.
- Transport problems, non-2xx HTTP statuses and malformed responses throw
  `Omnipay\Common\Exception\InvalidResponseException`.
- Missing/invalid request parameters throw `Omnipay\Common\Exception\InvalidRequestException` (or
  `InvalidCreditCardException` for card data) before anything is sent.

## Test card / environment

Ödeal shares test credentials and test cards with each merchant; sandbox credentials do not work on production and
vice versa.

## License

MIT — see [LICENSE](LICENSE).
