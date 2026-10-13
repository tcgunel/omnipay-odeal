<?php

namespace Omnipay\Odeal\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;

/**
 * Shared behaviour for every Ödeal response.
 *
 * Most endpoints answer with a `result` object carrying `success` and
 * `message`; check-status and transaction-details replace that with their
 * own payload, so the raw decoded body stays reachable through getData().
 */
abstract class RemoteAbstractResponse extends AbstractResponse
{
    /**
     * @var array<string, mixed>
     */
    protected array $result = [];

    /**
     * @param  mixed  $data
     */
    public function __construct(RequestInterface $request, $data)
    {
        parent::__construct($request, $data);

        $this->result = is_array($data) ? $data : [];
    }

    /**
     * The decoded response body.
     *
     * @return array<string, mixed>
     */
    public function getData()
    {
        return $this->result;
    }

    /**
     * Read a value from the decoded body by nested key path.
     */
    protected function value(string ...$path): mixed
    {
        $value = $this->result;

        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    protected function resultSuccess(): bool
    {
        return $this->value('result', 'success') === true;
    }

    protected function resultMessage(): ?string
    {
        $message = $this->value('result', 'message');

        return $message === null ? null : (string) $message;
    }

    protected function resultId(): ?string
    {
        $id = $this->value('result', 'id');

        return $id === null ? null : (string) $id;
    }
}
