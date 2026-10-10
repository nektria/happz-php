<?php

declare(strict_types=1);

namespace Xgc\Cache;

use RuntimeException;
use Throwable;
use Xgc\Dto\Clock;
use Xgc\Dto\ContextInterface;

use function count;
use function is_string;

/**
 * @template T
 */
abstract class InternalReferenceRedisCache extends RedisCache
{
    /**
     * @param InternalRedisCache<T> $internalRedisCache
     */
    public function __construct(
        private readonly InternalRedisCache $internalRedisCache,
        string $redisDsn,
        string $redisPrefix,
        ContextInterface $context,
    ) {
        parent::__construct($redisDsn, $context, $redisPrefix);
    }

    public function empty(): void
    {
        try {
            $this->init()->eval("for _,k in ipairs(redis.call('keys','{$this->fqn}:*')) do redis.call('del',k) end");
        } catch (Throwable) {
        }
    }

    public function fullRedisEmpty(): void
    {
        try {
            $this->init()->flushDB();
        } catch (Throwable) {
        }
    }

    /**
     * @return T|null
     */
    protected function getItem(string $key)
    {
        try {
            $luaScript = <<<LUA
                local key1 = KEYS[1]
                local key2 = redis.call('get', '{$this->fqn}:' .. key1)
                if key2 == nil or key2 == false then
                    return nil
                end
                return redis.call('get', '{$this->internalRedisCache->fqn}:' .. key2)
            LUA;

            /* @var string|false $item */
            $item = $this->init()->eval($luaScript, [$key], 1);

            if ($item === false) {
                return null;
            }

            if ($this->init()->getLastError() !== null) {
                $lastError = $this->init()->getLastError();
                $this->init()->clearLastError();

                throw new RuntimeException($lastError ?? '');
            }

            if (!is_string($item)) {
                return null;
            }

            return unserialize($item, ['allowed_classes' => true]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return T[]
     */
    protected function getItems(string $key): array
    {
        try {
            $luaScript = <<<LUA
                local key1 = KEYS[1]
                local key2 = redis.call('get', '{$this->fqn}:' .. key1)
                if key2 == nil or key2 == false then
                    return nil
                end
                local key2Array = {}

                if string.sub(key2, 1, 1) == "," then
                    key2 = string.sub(key2, 2)
                end

                for match in (key2..","):gmatch("(.-),") do
                    table.insert(key2Array, match)
                end

                local results = {}

                for _, ref in ipairs(key2Array) do
                    local value = redis.call('get', '{$this->internalRedisCache->fqn}:' .. ref)
                    table.insert(results, value)
                end

                return results
            LUA;

            /** @var string[]|false $items */
            $items = $this->init()->eval($luaScript, [$key], 1);

            if ($this->init()->getLastError() !== null) {
                $lastError = $this->init()->getLastError();
                $this->init()->clearLastError();

                throw new RuntimeException($lastError ?? '');
            }

            if ($items === false) {
                return [];
            }

            $result = [];
            foreach ($items as $item) {
                $result[] = unserialize($item, [
                    'allowed_classes' => true,
                ]);
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param string[] $keys
     * @return T[]
     */
    protected function getMultipleItems(array $keys): array
    {
        try {
            $luaScript = <<<LUA
                local results = {}
                local cache = {}
                for _, key1 in ipairs(KEYS) do
                    local key2 = redis.call('get', '{$this->fqn}:' .. key1)
                    if key2 ~= false and key2 ~= nil and not cache[key2] then
                        cache[key2] = true
                        local key2Array = {}

                        if string.sub(key2, 1, 1) == "," then
                            key2 = string.sub(key2, 2)
                        end

                        for match in (key2..","):gmatch("(.-),") do
                            table.insert(key2Array, '{$this->internalRedisCache->fqn}:' .. match)
                        end

                        local partialResults = redis.call('mget', unpack(key2Array))
                        table.insert(results, partialResults)
                    end
                end

                return results
            LUA;

            /** @var (string|false)[][]|false $items */
            $items = $this->init()->eval($luaScript, $keys, count($keys));

            if ($items === false) {
                return [];
            }

            if ($this->init()->getLastError() !== null) {
                $lastError = $this->init()->getLastError();
                $this->init()->clearLastError();

                throw new RuntimeException($lastError ?? '');
            }

            $result = [];

            foreach ($items as $item) {
                foreach ($item as $subItem) {
                    if ($subItem !== false) {
                        $result[] = unserialize($subItem, [
                            'allowed_classes' => true,
                        ]);
                    }
                }
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param string[] $keys
     * @return T[]
     */
    protected function getPartialItems(array $keys): array
    {
        try {
            $luaScript = <<<LUA
            local results = {}
            local cache = {}
            for _, key1 in ipairs(KEYS) do
                local cursor = "0"
                repeat
                    local scanResult = redis.call("SCAN", cursor, "MATCH", '{$this->fqn}:' .. key1 .. '*')
                    cursor = scanResult[1]
                    local keys = scanResult[2]
                    for _, key2 in ipairs(keys) do
                        if not cache[key2] then
                            cache[key2] = true
                            local value = redis.call("GET", key2)
                            if value ~= false and value ~= nil then
                                local key2Array = {}
                                if string.sub(value, 1, 1) == "," then
                                    value = string.sub(value, 2)
                                end
                                for match in (value..","):gmatch("(.-),") do
                                    table.insert(key2Array, '{$this->internalRedisCache->fqn}:' .. match)
                                end
                                local partialResults = redis.call('mget', unpack(key2Array))
                                table.insert(results, partialResults)
                            end
                        end
                    end
                until cursor == "0"
            end

            return results

            LUA;

            /** @var (string|false)[][]|false $items */
            $items = $this->init()->eval($luaScript, $keys, count($keys));

            if ($items === false) {
                return [];
            }

            if ($this->init()->getLastError() !== null) {
                $lastError = $this->init()->getLastError();
                $this->init()->clearLastError();

                throw new RuntimeException($lastError ?? '');
            }

            $result = [];
            foreach ($items as $item) {
                foreach ($item as $subItem) {
                    if ($subItem !== false) {
                        $result[] = unserialize($subItem, [
                            'allowed_classes' => true,
                        ]);
                    }
                }
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    protected function getRawItem(string $key): ?string
    {
        try {
            /** @var string|false $item */
            $item = $this->init()->get("{$this->fqn}:{$key}");

            if ($item === false) {
                return null;
            }

            return $item;
        } catch (Throwable) {
            return null;
        }
    }

    protected function removeItem(string $key): void
    {
        try {
            $this->init()->del("{$this->fqn}:{$key}");
        } catch (Throwable) {
        }
    }

    /**
     * @param string[] $items
     */
    protected function setListItems(string $key, array $items, Clock | int $ttl = 300): void
    {
        if ($ttl instanceof Clock) {
            $ttl = $ttl->diff(Clock::now());
        }

        if (count($items) === 0) {
            $this->removeItem($key);
        }

        try {
            $item = implode(',', $items);
            $this->init()->set("{$this->fqn}:{$key}", $item, ['ex' => $ttl]);
        } catch (Throwable) {
        }
    }

    protected function setRawItem(string $key, string $item, Clock | int $ttl = 300): void
    {
        if ($ttl instanceof Clock) {
            $ttl = $ttl->diff(Clock::now());
        }

        try {
            $this->init()->set("{$this->fqn}:{$key}", $item, ['ex' => $ttl]);
        } catch (Throwable) {
        }
    }
}
