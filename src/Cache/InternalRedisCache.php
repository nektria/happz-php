<?php

declare(strict_types=1);

namespace Xgc\Cache;

use RuntimeException;
use Throwable;
use Xgc\Dto\Clock;
use Xgc\Dto\ContextInterface;

use function is_bool;
use function is_string;

/**
 * @template T
 */
abstract class InternalRedisCache extends RedisCache
{
    public function __construct(string $redisDsn, ContextInterface $context)
    {
        parent::__construct($redisDsn, $context, $context->project());

        $data = parse_url($redisDsn);
        if ($data === false) {
            throw new RuntimeException("Invalid redis dsn: {$redisDsn}.");
        }
    }

    public function beginTransaction(): void
    {
        $this->init()->multi();
    }

    public function closeTransaction(): void
    {
        $this->init()->exec();
    }

    public function empty(): void
    {
        try {
            $this->init()->eval("for _,k in ipairs(redis.call('keys','{$this->fqn}:*')) do redis.call('del',k) end");

            if ($this->init()->getLastError() !== null) {
                $lastError = $this->init()->getLastError();
                $this->init()->clearLastError();

                throw new RuntimeException($lastError ?? '');
            }
        } catch (Throwable) {
        }
    }

    public function fullRedisEmpty(): void
    {
        try {
            $this->init()->flushDB();

            if ($this->init()->getLastError() !== null) {
                $lastError = $this->init()->getLastError();
                $this->init()->clearLastError();

                throw new RuntimeException($lastError ?? '');
            }
        } catch (Throwable) {
        }
    }

    /**
     * @return T|null
     */
    protected function getItem(string $key): mixed
    {
        try {
            $item = $this->init()->get("{$this->fqn}:{$key}");

            if (is_bool($item)) {
                return null;
            }

            if (!is_string($item)) {
                return null;
            }

            if ($this->init()->getLastError() !== null) {
                $lastError = $this->init()->getLastError();
                $this->init()->clearLastError();

                throw new RuntimeException($lastError ?? '');
            }

            $ser = unserialize($item, [
                'allowed_classes' => true,
            ]);

            if (is_bool($ser)) {
                return null;
            }

            return $ser;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param string[] $keys
     * @return array<string, T|null>
     * @return ($returnMissing is true ? array<string, T|null> : array<string, T>)
     */
    protected function getItems(array $keys, bool $returnMissing = false): array
    {
        try {
            $realKeys = array_map(fn (string $key) => "{$this->fqn}:{$key}", $keys);
            $items = $this->init()->mget($realKeys);

            $results = [];
            foreach ($keys as $index => $key) {
                if ($items[$index] === false) {
                    if ($returnMissing) {
                        $results[$key] = null;
                    }
                } else {
                    if (!is_string($items[$index])) {
                        continue;
                    }

                    $results[$key] = unserialize($items[$index], [
                        'allowed_classes' => true,
                    ]);
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $results;
    }

    protected function removeItem(string $key): void
    {
        try {
            $this->init()->del("{$this->fqn}:{$key}");

            if ($this->init()->getLastError() !== null) {
                $lastError = $this->init()->getLastError();
                $this->init()->clearLastError();

                throw new RuntimeException($lastError ?? '');
            }
        } catch (Throwable) {
        }
    }

    /**
     * @param T $item
     */
    protected function setItem(string $key, $item, Clock | int $ttl = 300): void
    {
        if ($ttl instanceof Clock) {
            $ttl = $ttl->diff(Clock::now());
        }

        $ttl = max(1, $ttl);

        try {
            $this->init()->set("{$this->fqn}:{$key}", serialize($item), ['ex' => $ttl]);

            if ($this->init()->getLastError() !== null) {
                $lastError = $this->init()->getLastError();
                $this->init()->clearLastError();

                throw new RuntimeException($lastError ?? '');
            }
        } catch (Throwable) {
        }
    }
}
