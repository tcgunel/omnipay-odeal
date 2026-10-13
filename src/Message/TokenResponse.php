<?php

namespace Omnipay\Odeal\Message;

/**
 * Response of POST /token.
 */
class TokenResponse extends RemoteAbstractResponse
{
    public function isSuccessful(): bool
    {
        $token = $this->getAccessToken();

        return $token !== null && $token !== '';
    }

    public function getAccessToken(): ?string
    {
        $token = $this->value('accessToken');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Token lifetime in seconds (~1799).
     */
    public function getExpiresIn(): ?int
    {
        $expiresIn = $this->value('expiresIn');

        return is_numeric($expiresIn) ? (int) $expiresIn : null;
    }

    public function getTokenType(): ?string
    {
        $type = $this->value('tokenType');

        return is_string($type) ? $type : null;
    }

    public function getScope(): ?string
    {
        $scope = $this->value('scope');

        return is_string($scope) ? $scope : null;
    }

    public function getMessage(): ?string
    {
        foreach (['error_description', 'message', 'error'] as $key) {
            $message = $this->value($key);

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return null;
    }
}
