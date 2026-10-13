<?php

namespace Omnipay\Odeal\Traits;

use Psr\SimpleCache\CacheInterface;

/**
 * Shared getters/setters for the gateway and every request.
 *
 * The gateway holds these parameters in its parameter bag; createRequest()
 * copies them into each request, so both classes use the same trait. Common
 * Omnipay parameters (amount, currency, transactionId, returnUrl, card, ...)
 * are provided by omnipay/common itself.
 */
trait HasParameters
{
    public function getClientId()
    {
        return $this->getParameter('clientId');
    }

    public function setClientId($value)
    {
        return $this->setParameter('clientId', $value);
    }

    public function getClientSecret()
    {
        return $this->getParameter('clientSecret');
    }

    public function setClientSecret($value)
    {
        return $this->setParameter('clientSecret', $value);
    }

    /**
     * Optional value for the X-ODEAL-AGENT header. Ödeal may ask integration
     * partners to identify themselves through this header.
     */
    public function getAgent()
    {
        return $this->getParameter('agent');
    }

    public function setAgent($value)
    {
        return $this->setParameter('agent', $value);
    }

    /**
     * Overrides the token base URL (defaults to the official endpoint of the
     * selected environment). Include everything up to, but not including,
     * the final "/token".
     */
    public function getTokenUrl()
    {
        return $this->getParameter('tokenUrl');
    }

    public function setTokenUrl($value)
    {
        return $this->setParameter('tokenUrl', $value);
    }

    /**
     * Overrides the gateway base URL (defaults to the official endpoint of
     * the selected environment). Include everything up to, but not
     * including, "/init-3d" and friends.
     */
    public function getGatewayUrl()
    {
        return $this->getParameter('gatewayUrl');
    }

    public function setGatewayUrl($value)
    {
        return $this->setParameter('gatewayUrl', $value);
    }

    /**
     * Optional PSR-16 cache used to share the bearer token across processes.
     *
     * Issuing a new token invalidates the previous one, so a shared cache
     * avoids needless token churn when several requests run in parallel.
     */
    public function getTokenCache(): ?CacheInterface
    {
        $cache = $this->getParameter('tokenCache');

        return $cache instanceof CacheInterface ? $cache : null;
    }

    public function setTokenCache(?CacheInterface $cache): static
    {
        return $this->setParameter('tokenCache', $cache);
    }

    public function getTokenCachePrefix(): string
    {
        return (string) ($this->getParameter('tokenCachePrefix') ?: 'odeal.token.');
    }

    public function setTokenCachePrefix(string $prefix): static
    {
        return $this->setParameter('tokenCachePrefix', $prefix);
    }

    /**
     * Seconds spent from the token lifetime before it is treated as expired,
     * so a call never starts with a token that is about to die.
     */
    public function getTokenCacheSkew(): int
    {
        return (int) ($this->getParameter('tokenCacheSkew') ?? 30);
    }

    public function setTokenCacheSkew(int $seconds): static
    {
        return $this->setParameter('tokenCacheSkew', $seconds);
    }

    /**
     * Three-dimensional secure flag. purchase() issues a 3D (init-3d) request
     * by default; pass secure => false (or use purchaseNon3D()) for a direct
     * non-3D charge.
     */
    public function getSecure()
    {
        return $this->getParameter('secure');
    }

    public function setSecure($value)
    {
        return $this->setParameter('secure', $value);
    }

    public function getInstallment()
    {
        return $this->getParameter('installment');
    }

    public function setInstallment($value)
    {
        return $this->setParameter('installment', $value);
    }

    /**
     * Merchant-side unique reference of the operation, used for reconciliation
     * (mutabakat) and accepted by every transaction operation as an
     * alternative to Ödeal's own id.
     */
    public function getExternalId()
    {
        return $this->getParameter('externalId');
    }

    public function setExternalId($value)
    {
        return $this->setParameter('externalId', $value);
    }

    public function getPhone()
    {
        return $this->getParameter('phone');
    }

    public function setPhone($value)
    {
        return $this->setParameter('phone', $value);
    }

    public function getBuyerName()
    {
        return $this->getParameter('buyerName');
    }

    public function setBuyerName($value)
    {
        return $this->setParameter('buyerName', $value);
    }

    public function getBuyerCity()
    {
        return $this->getParameter('buyerCity');
    }

    public function setBuyerCity($value)
    {
        return $this->setParameter('buyerCity', $value);
    }

    public function getBuyerMail()
    {
        return $this->getParameter('buyerMail');
    }

    public function setBuyerMail($value)
    {
        return $this->setParameter('buyerMail', $value);
    }

    public function getBuyerAddress()
    {
        return $this->getParameter('buyerAddress');
    }

    public function setBuyerAddress($value)
    {
        return $this->setParameter('buyerAddress', $value);
    }

    public function getSuccessRedirectUrl()
    {
        return $this->getParameter('successRedirectUrl');
    }

    public function setSuccessRedirectUrl($value)
    {
        return $this->setParameter('successRedirectUrl', $value);
    }

    public function getFailureRedirectUrl()
    {
        return $this->getParameter('failureRedirectUrl');
    }

    public function setFailureRedirectUrl($value)
    {
        return $this->setParameter('failureRedirectUrl', $value);
    }

    public function getLatitude()
    {
        return $this->getParameter('latitude');
    }

    public function setLatitude($value)
    {
        return $this->setParameter('latitude', $value);
    }

    public function getLongitude()
    {
        return $this->getParameter('longitude');
    }

    public function setLongitude($value)
    {
        return $this->setParameter('longitude', $value);
    }

    /**
     * Ödeal's own transaction id (returned by the payment endpoints and
     * required by cancel/refund/status).
     */
    public function getId()
    {
        return $this->getParameter('id');
    }

    public function setId($value)
    {
        return $this->setParameter('id', $value);
    }

    public function getReason()
    {
        return $this->getParameter('reason');
    }

    public function setReason($value)
    {
        return $this->setParameter('reason', $value);
    }

    public function getReasonType()
    {
        return $this->getParameter('reasonType');
    }

    public function setReasonType($value)
    {
        return $this->setParameter('reasonType', $value);
    }
}
