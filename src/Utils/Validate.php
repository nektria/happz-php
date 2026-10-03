<?php

declare(strict_types=1);

namespace Xgc\Utils;

use ReflectionProperty;
use Throwable;
use Xgc\Dto\Clock;
use Xgc\Dto\LocalClock;
use Xgc\Exception\BaseException;
use Xgc\Exception\InvalidArgumentException;
use Xgc\Exception\MissingFieldRequiredToCreateClassException;

use function count;
use function in_array;
use function is_callable;

use function strlen;

use const FILTER_VALIDATE_EMAIL;

/**
 * @phpstan-import-type CtTimeFormat from Clock
 */
class Validate
{
    public static function classFieldReturnsNotNull(string $className, string $name, mixed $field): void
    {
        if ($field === null) {
            throw new MissingFieldRequiredToCreateClassException($className, $name);
        }
    }

    /**
     * @param string[] $fields
     */
    public static function classFieldsReturnsNotNull(object $object, string $className, array $fields): void
    {
        foreach ($fields as $field) {
            self::checkClassFieldReturnsNotNull($object, $className, $field);
        }
    }

    public static function color(string $field, string $value): void
    {
        if (preg_match('/#([a-f0-9]{3}){1,2}\b/i', $value) === false) {
            throw new InvalidArgumentException($field, $value, 'color');
        }
    }

    // Strings

    public static function date(string $field, string $date): void
    {
        $parsed = Clock::fromString($date);

        if ($parsed->dateString() !== $date) {
            throw new InvalidArgumentException($field, $date, 'date');
        }
    }

    public static function email(string $field, string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException($field, $value, 'email');
        }
    }

    public static function greaterOrEqualThan(
        string $field,
        int | float | string $value,
        int | float | string $limit,
    ): void {
        if ((string) $value < (string) $limit) {
            throw new InvalidArgumentException($field, $value, condition: ">= {$limit}");
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function greaterOrEqualThanClock(
        string $field,
        Clock $value,
        Clock $limit,
        string $in = 'seconds',
    ): void {
        if ($value->isBefore($limit, $in)) {
            throw new InvalidArgumentException(
                $field,
                $value->dateTimeString(),
                condition: ">= {$limit->dateTimeString()}",
            );
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function greaterOrEqualThanLocalClock(
        string $field,
        LocalClock $value,
        LocalClock $limit,
        string $in = 'seconds',
    ): void {
        if ($value->isBefore($limit, $in)) {
            throw new InvalidArgumentException(
                $field,
                $value->dateTimeString(),
                condition: ">= {$limit->dateTimeString()}",
            );
        }
    }

    public static function greaterThan(
        string $field,
        int | float | string $value,
        int | float | string $limit,
    ): void {
        if ((string) $value <= (string) $limit) {
            throw new InvalidArgumentException($field, $value, condition: "> {$limit}");
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function greaterThanClock(
        string $field,
        Clock $value,
        Clock $limit,
        string $in = 'seconds',
    ): void {
        if ($value->isBeforeOrEqual($limit, $in)) {
            throw new InvalidArgumentException(
                $field,
                $value->dateTimeString(),
                condition: "> {$limit->dateTimeString()}",
            );
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function greaterThanLocalClock(
        string $field,
        LocalClock $value,
        LocalClock $limit,
        string $in = 'seconds',
    ): void {
        if ($value->isBeforeOrEqual($limit, $in)) {
            throw new InvalidArgumentException(
                $field,
                $value->dateTimeString(),
                condition: "> {$limit->dateTimeString()}",
            );
        }
    }

    /**
     * @param string[] $values
     */
    public static function inStringList(string $field, string $value, array $values): void
    {
        if (!in_array($value, $values, true)) {
            $validValues = implode(', ', $values);

            throw new InvalidArgumentException($field, $value, $validValues);
        }
    }

    public static function latitude(string $field, float $value): void
    {
        if ($value < -90 || $value > 90) {
            throw new InvalidArgumentException($field, $value, 'latitude');
        }
    }

    public static function lessOrEqualThan(string $field, int | float $value, int | float $limit): void
    {
        if ($value > $limit) {
            throw new InvalidArgumentException($field, $value, condition: "<= {$limit}");
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function lessOrEqualThanClock(string $field, Clock $value, Clock $limit, string $in = 'seconds'): void
    {
        if ($value->isAfter($limit, $in)) {
            throw new InvalidArgumentException(
                $field,
                $value->dateTimeString(),
                condition: "<= {$limit->dateTimeString()}",
            );
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function lessOrEqualThanLocalClock(
        string $field,
        LocalClock $value,
        LocalClock $limit,
        string $in = 'seconds',
    ): void {
        if ($value->isAfter($limit, $in)) {
            throw new InvalidArgumentException(
                $field,
                $value->dateTimeString(),
                condition: "<= {$limit->dateTimeString()}",
            );
        }
    }

    public static function lessThan(string $field, int | float $value, int | float $limit): void
    {
        if ($value >= $limit) {
            throw new InvalidArgumentException($field, $value, condition: "< {$limit}");
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function lessThanClock(string $field, Clock $value, Clock $limit, string $in = 'seconds'): void
    {
        if ($value->isAfterOrEqual($limit, $in)) {
            throw new InvalidArgumentException(
                $field,
                $value->dateTimeString(),
                condition: "< {$limit->dateTimeString()}",
            );
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function lessThanLocalClock(
        string $field,
        LocalClock $value,
        LocalClock $limit,
        string $in = 'seconds',
    ): void {
        if ($value->isAfterOrEqual($limit, $in)) {
            throw new InvalidArgumentException(
                $field,
                $value->dateTimeString(),
                condition: "< {$limit->dateTimeString()}",
            );
        }
    }

    public static function longitude(string $field, float $value): void
    {
        if ($value < -180 || $value > 180) {
            throw new InvalidArgumentException($field, $value, 'longitude');
        }
    }

    /**
     * @param mixed[] $list
     */
    public static function maxArrayLength(string $field, array $list, int $length): void
    {
        $count = count($list);
        if ($count > $length) {
            throw new InvalidArgumentException($field, "[{$count}]", condition: "size <= {$length}");
        }
    }

    public static function maxStringLength(string $field, string $value, int $length): void
    {
        if (strlen($value) > $length) {
            throw new InvalidArgumentException($field, $value, condition: "size <= {$length}");
        }
    }

    /**
     * @param mixed[] $list
     */
    public static function minArrayLength(string $field, array $list, int $length): void
    {
        $count = count($list);
        if ($count < $length) {
            throw new InvalidArgumentException($field, "[{$count}]", condition: "size >= {$length}");
        }
    }

    public static function minStringLength(string $field, string $value, int $length): void
    {
        if (strlen($value) < $length) {
            throw new InvalidArgumentException($field, $value, condition: "size >= {$length}");
        }
    }

    public static function naturalNumber(string $field, int | float $number): void
    {
        self::greaterOrEqualThan($field, $number, 0);
    }

    public static function notEmpty(string $field, string $value): void
    {
        self::minStringLength($field, $value, 1);
    }

    public static function notNull(string $field, mixed $value, string $condition): void
    {
        if ($value === null) {
            throw new InvalidArgumentException($field, $value, condition: $condition);
        }
    }

    public static function pagination(int $page, int $maxResults): void
    {
        if ($maxResults < 1 || $maxResults >= 1000) {
            throw new InvalidArgumentException(
                'maxResults',
                $maxResults,
                condition: 'AND maxResults >= 1 AND maxResults < 1000',
            );
        }

        if ($page < 1) {
            throw new InvalidArgumentException(
                'page',
                $page,
                condition: 'page >= 1',
            );
        }
    }

    public static function percentileNumber(string $field, int | float $number): void
    {
        self::greaterOrEqualThan($field, $number, 0);
        self::lessOrEqualThan($field, $number, 100);
    }

    public static function regexp(string $field, string $value, string $regexp): void
    {
        if (preg_match($regexp, $value) === false) {
            throw new InvalidArgumentException($field, $value, $regexp);
        }
    }

    public static function timezone(string $field, string $timezone): void
    {
        try {
            Clock::fromString('now')->toLocal($timezone);
        } catch (Throwable) {
            throw new InvalidArgumentException($field, $timezone, 'timezone');
        }
    }

    // numbers

    public static function uuid4(string $field, string $id): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) !== 1) {
            throw new InvalidArgumentException($field, $id, 'uuid4');
        }
    }

    private static function checkClassFieldReturnsNotNull(object $object, string $className, string $field): void
    {
        if (method_exists($object, $field)) {
            try {
                $getter = [$object, $field];
                if (!is_callable($getter)) {
                    throw new BaseException("{$className} does not implements {$field}()");
                }

                if ($getter() === null) {
                    throw new MissingFieldRequiredToCreateClassException($className, $field);
                }
            } catch (Throwable $e) {
                if ($e instanceof MissingFieldRequiredToCreateClassException) {
                    throw $e;
                }

                throw new BaseException("{$className} does not implements {$field}()");
            }
        } elseif (property_exists($object, $field)) {
            $property = new ReflectionProperty($object, $field);
            if (!$property->isPublic()) {
                throw new BaseException("{$className} does not implements {$field}()");
            }

            if ($property->getValue($object) === null) {
                throw new MissingFieldRequiredToCreateClassException($className, $field);
            }
        } else {
            throw new BaseException("{$className} does not implements {$field}()");
        }
    }
}
