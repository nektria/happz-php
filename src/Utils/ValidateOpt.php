<?php

declare(strict_types=1);

namespace Xgc\Utils;

use Xgc\Dto\Clock;
use Xgc\Dto\LocalClock;

/**
 * @phpstan-import-type CtTimeFormat from Clock
 */
readonly class ValidateOpt
{
    public static function color(string $field, ?string $value): void
    {
        if ($value !== null) {
            Validate::color($field, $value);
        }
    }

    public static function date(string $field, ?string $date): void
    {
        if ($date !== null) {
            Validate::date($field, $date);
        }
    }

    public static function email(string $field, ?string $value): void
    {
        if ($value !== null) {
            Validate::email($field, $value);
        }
    }

    public static function greaterOrEqualThan(string $field, int | float | null $number, int | float $limit): void
    {
        if ($number !== null) {
            Validate::greaterOrEqualThan($field, $number, $limit);
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function greaterOrEqualThanClock(
        string $field,
        ?Clock $value,
        ?Clock $limit,
        string $in = 'seconds',
    ): void {
        if ($value !== null && $limit !== null) {
            Validate::greaterOrEqualThanClock($field, $value, $limit, $in);
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function greaterOrEqualThanLocalClock(
        string $field,
        ?LocalClock $value,
        ?LocalClock $limit,
        string $in = 'seconds',
    ): void {
        if ($value !== null && $limit !== null) {
            Validate::greaterOrEqualThanLocalClock($field, $value, $limit, $in);
        }
    }

    public static function greaterThan(string $field, int | float | null $number, int | float $limit): void
    {
        if ($number !== null) {
            Validate::greaterThan($field, $number, $limit);
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function greaterThanClock(
        string $field,
        ?Clock $value,
        ?Clock $limit,
        string $in = 'seconds',
    ): void {
        if ($value !== null && $limit !== null) {
            Validate::greaterThanClock($field, $value, $limit, $in);
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function greaterThanLocalClock(
        string $field,
        ?LocalClock $value,
        ?LocalClock $limit,
        string $in = 'seconds',
    ): void {
        if ($value !== null && $limit !== null) {
            Validate::greaterThanLocalClock($field, $value, $limit, $in);
        }
    }

    /**
     * @param string[] $validValues
     */
    public static function inStringList(string $field, ?string $value, array $validValues): void
    {
        if ($value !== null) {
            Validate::inStringList($field, $value, $validValues);
        }
    }

    public static function latitude(string $field, ?float $value): void
    {
        if ($value !== null) {
            Validate::latitude($field, $value);
        }
    }

    public static function lessOrEqualThan(string $field, int | float | null $value, int | float $limit): void
    {
        if ($value !== null) {
            Validate::lessOrEqualThan($field, $value, $limit);
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function lessOrEqualThanClock(
        string $field,
        ?Clock $value,
        ?Clock $limit,
        string $in = 'seconds',
    ): void {
        if ($value !== null && $limit !== null) {
            Validate::lessOrEqualThanClock($field, $value, $limit, $in);
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function lessOrEqualThanLocalClock(
        string $field,
        ?LocalClock $value,
        ?LocalClock $limit,
        string $in = 'seconds',
    ): void {
        if ($value !== null && $limit !== null) {
            Validate::lessOrEqualThanLocalClock($field, $value, $limit, $in);
        }
    }

    public static function lessThan(string $field, int | float | null $number, int | float $limit): void
    {
        if ($number !== null) {
            Validate::lessThan($field, $number, $limit);
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function lessThanClock(string $field, ?Clock $value, ?Clock $limit, string $in = 'seconds'): void
    {
        if ($value !== null && $limit !== null) {
            Validate::lessThanClock($field, $value, $limit, $in);
        }
    }

    /**
     * @param CtTimeFormat $in
     */
    public static function lessThanLocalClock(
        string $field,
        ?LocalClock $value,
        ?LocalClock $limit,
        string $in = 'seconds',
    ): void {
        if ($value !== null && $limit !== null) {
            Validate::lessThanLocalClock($field, $value, $limit, $in);
        }
    }

    public static function longitude(string $field, ?float $value): void
    {
        if ($value !== null) {
            Validate::longitude($field, $value);
        }
    }

    public static function maxStringLength(string $field, ?string $value, int $length): void
    {
        if ($value !== null) {
            Validate::maxStringLength($field, $value, $length);
        }
    }

    /**
     * @param mixed[]|null $list
     */
    public static function minArrayLength(string $field, ?array $list, int $length): void
    {
        if ($list !== null) {
            Validate::minArrayLength($field, $list, $length);
        }
    }

    public static function minStringLength(string $field, ?string $value, int $length): void
    {
        if ($value !== null) {
            Validate::minStringLength($field, $value, $length);
        }
    }

    public static function naturalNumber(string $field, int | float | null $number): void
    {
        if ($number !== null) {
            Validate::naturalNumber($field, $number);
        }
    }

    // coordinates

    public static function notEmpty(string $field, ?string $value): void
    {
        if ($value !== null) {
            Validate::notEmpty($field, $value);
        }
    }

    public static function pagination(?int $page, ?int $maxResults): void
    {
        Validate::pagination($page ?? 1, $maxResults ?? 1);
    }

    public static function percentileNumber(string $field, int | float | null $number): void
    {
        if ($number !== null) {
            Validate::percentileNumber($field, $number);
        }
    }

    public static function regexp(string $field, ?string $value, string $regexp): void
    {
        if ($value !== null) {
            Validate::regexp($field, $value, $regexp);
        }
    }

    public static function timezone(string $field, ?string $value): void
    {
        if ($value !== null) {
            Validate::timezone($field, $value);
        }
    }

    public static function uuid4(string $field, ?string $value): void
    {
        if ($value !== null) {
            Validate::uuid4($field, $value);
        }
    }
}
