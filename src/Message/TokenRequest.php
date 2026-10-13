<?php

namespace Omnipay\Odeal\Message;

/**
 * POST /token - fetch a bearer token for the virtual POS API.
 *
 * The gateway already fetches tokens lazily inside every operation; this
 * request is exposed for credential checks (a shop panel "test connection"
 * button). Note that issuing a token invalidates the previous one, so the
 * shared token cache is refreshed afterwards.
 */
class TokenRequest extends RemoteAbstractRequest
{
    /**
     * @return array<string, string>
     */
    public function getData(): array
    {
        $this->validateCredentials();

        return $this->tokenPayload();
    }

    /**
     * @param  array<string, string>  $data
     */
    public function sendData($data): TokenResponse
    {
        $result = $this->sendJsonRequest($this->getTokenEndpoint(), $data, false);

        $this->logOperationResult('token', [
            'result' => [
                'success' => isset($result['accessToken']),
            ],
        ]);

        return $this->createResponse($result);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createResponse($data): TokenResponse
    {
        return $this->response = new TokenResponse($this, $data);
    }
}
