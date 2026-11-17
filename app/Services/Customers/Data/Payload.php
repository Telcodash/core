<?php

namespace App\Services\Customers\Data;

use Carbon\CarbonImmutable;
use UnexpectedValueException;

/**
 * Strict readers for daemon responses: a malformed payload fails loudly instead of leaking
 * nulls or wrong types into the app.
 *
 * @internal
 */
final class Payload
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw new UnexpectedValueException("Expected string at [{$key}] in daemon response.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        return ($data[$key] ?? null) === null ? null : self::string($data, $key);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (! is_int($value)) {
            throw new UnexpectedValueException("Expected integer at [{$key}] in daemon response.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        return ($data[$key] ?? null) === null ? null : self::int($data, $key);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        if (! is_bool($value)) {
            throw new UnexpectedValueException("Expected boolean at [{$key}] in daemon response.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function date(array $data, string $key): CarbonImmutable
    {
        return CarbonImmutable::parse(self::string($data, $key));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableDate(array $data, string $key): ?CarbonImmutable
    {
        return ($data[$key] ?? null) === null ? null : self::date($data, $key);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function object(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            throw new UnexpectedValueException("Expected object at [{$key}] in daemon response.");
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            throw new UnexpectedValueException("Expected list at [{$key}] in daemon response.");
        }

        /** @var list<array<string, mixed>> $value */
        return $value;
    }
}
