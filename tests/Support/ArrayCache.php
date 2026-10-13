<?php

namespace Omnipay\Odeal\Tests\Support;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Minimal in-memory PSR-16 cache for the token-cache tests; records the TTL
 * of every entry so tests can assert the expiry skew.
 */
class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $values = [];

    /**
     * @var array<string, int|null>
     */
    public array $ttls = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->values[$key] = $value;
        $this->ttls[$key] = is_int($ttl) ? $ttl : null;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key], $this->ttls[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];
        $this->ttls = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }
}
