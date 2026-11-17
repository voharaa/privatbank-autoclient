<?php

namespace Voharaa\PrivatbankAutoclient\Support;

use Voharaa\PrivatbankAutoclient\Exceptions\TransportException;

/**
 * Typed reads from a decoded API response.
 *
 * Throws TransportException when a required field is missing or has an unexpected type,
 * so a changed response format surfaces as a library exception instead of a TypeError.
 *
 * @internal
 */
final class Payload
{
    /**
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public static function string(array $data, string $key): string
    {
        return self::optionalString($data, $key)
            ?? throw new TransportException("Unexpected response format: missing field \"{$key}\"");
    }

    /**
     * Null when the field is missing or null.
     *
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => throw new TransportException("Unexpected response format: field \"{$key}\" is not a string"),
        };
    }

    /**
     * Null when the field is missing, null or blank.
     *
     * @param  array<mixed>  $data
     *
     * @throws TransportException
     */
    public static function nonBlankString(array $data, string $key): ?string
    {
        $value = self::optionalString($data, $key);

        return $value === null || trim($value) === '' ? null : $value;
    }

    /**
     * Reads a required field as a string-backed enum.
     *
     * An unknown value (e.g. a new status added by the bank) is reported as a TransportException,
     * not as a ValueError, so it is caught together with other response format errors.
     *
     * @template T of \BackedEnum
     *
     * @param  array<mixed>  $data
     * @param  class-string<T>  $enum
     * @return T
     *
     * @throws TransportException
     */
    public static function enum(array $data, string $key, string $enum): \BackedEnum
    {
        $value = self::string($data, $key);

        return $enum::tryFrom($value)
            ?? throw new TransportException("Unexpected response format: unknown value \"{$value}\" in field \"{$key}\"");
    }

    /**
     * Accepts real booleans and "true"/"false" strings. False when missing.
     *
     * @param  array<mixed>  $data
     */
    public static function bool(array $data, string $key): bool
    {
        return filter_var($data[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     *
     * @throws TransportException
     */
    public static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value)
            ? $value
            : throw new TransportException("Unexpected response format: field \"{$key}\" is not an object or a list");
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     *
     * @throws TransportException
     */
    public static function stringList(array $data, string $key): array
    {
        $list = [];

        foreach (self::array($data, $key) as $index => $value) {
            $list[] = self::string([$index => $value], (string) $index);
        }

        return $list;
    }
}
