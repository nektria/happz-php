<?php

declare(strict_types=1);

namespace Xgc\Utils;

use Random\RandomException;
use Xgc\Exception\BaseException;

use function count;
use function sprintf;
use function strlen;

use const PHP_FLOAT_MAX;
use const PHP_FLOAT_MIN;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

class RandomUtil
{
    public static function character(string $text): string
    {
        $length = strlen($text);

        return $text[self::int(0, $length - 1)];
    }

    /**
     * @template T
     * @param T[] $list
     * @return T
     */
    public static function element(array $list): mixed
    {
        return $list[self::int(0, count($list) - 1)];
    }

    public static function float(float $min = PHP_FLOAT_MIN, float $max = PHP_FLOAT_MAX): float
    {
        return $min + (self::int() / PHP_INT_MAX) * ($max - $min);
    }

    public static function int(int $from = PHP_INT_MIN, int $to = PHP_INT_MAX): int
    {
        try {
            return random_int($from, $to);
        } catch (RandomException $e) {
            throw BaseException::extend($e);
        }
    }

    public static function string(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (RandomException $e) {
            throw BaseException::extend($e);
        }
    }

    public static function uuid4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            self::int(0, 0xFFFF),
            self::int(0, 0xFFFF),
            self::int(0, 0xFFFF),
            self::int(0, 0x0FFF) | 0x4000,
            self::int(0, 0x3FFF) | 0x8000,
            self::int(0, 0xFFFF),
            self::int(0, 0xFFFF),
            self::int(0, 0xFFFF),
        );
    }
}
