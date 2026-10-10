<?php

declare(strict_types=1);

namespace Xgc\Cache;

use Throwable;
use Xgc\Dto\Clock;
use Xgc\Dto\ContextInterface;
use Xgc\Utils\Validate;

use function is_string;

/**
 * @template T
 */
abstract class SharedRedisCache extends RedisCache
{
    public function __construct(
        string $redisDsn,
        ContextInterface $context,
    ) {
        parent::__construct($redisDsn, $context, 'shared');
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
        } catch (Throwable) {
        }
    }

    /**
     * @return T|null
     */
    protected function getItem(string $key): mixed
    {
        Validate::notEmpty('redis_key', $key);

        try {
            $item = $this->init()->get("{$this->fqn}:{$key}");

            if (!is_string($item)) {
                return null;
            }

            return unserialize($item, ['allowed_classes' => true]);
        } catch (Throwable) {
            return null;
        }
    }

    protected function removeItem(string $key): void
    {
        Validate::notEmpty('redis_key', $key);

        try {
            $this->init()->del("{$this->fqn}:{$key}");
        } catch (Throwable) {
        }
    }

    /**
     * @param T $item
     */
    protected function setItem(string $key, $item, Clock | int $ttl = 300): void
    {
        Validate::notEmpty('redis_key', $key);

        if ($ttl instanceof Clock) {
            $ttl = $ttl->diff(Clock::now());
        }

        try {
            $this->init()->set("{$this->fqn}:{$key}", serialize($item), ['ex' => $ttl]);
        } catch (Throwable) {
        }
    }
}
