<?php

namespace App\Domain\Audit\Data;

use InvalidArgumentException;
use JsonException;

final readonly class AuditProperties
{
    private const MAX_ENCODED_BYTES = 16_384;

    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'token',
        'secret',
        'session',
        'cookie',
        'authorization',
        'credentials',
    ];

    /**
     * @param  array<int|string, mixed>  $values
     */
    public function __construct(private array $values)
    {
        try {
            $encoded = json_encode($values, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Audit properties must be valid JSON.', previous: $exception);
        }

        self::validateArray($values);

        if (strlen($encoded) > self::MAX_ENCODED_BYTES) {
            throw new InvalidArgumentException('Audit properties exceed 16,384 encoded bytes.');
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @param  array<int|string, mixed>  $values
     */
    private static function validateArray(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                throw new InvalidArgumentException("Sensitive audit property key [{$key}] is forbidden.");
            }

            if (is_array($value)) {
                self::validateArray($value);

                continue;
            }

            if (! is_null($value) && ! is_bool($value) && ! is_int($value) && ! is_string($value)) {
                throw new InvalidArgumentException('Audit properties accept only JSON-safe non-floating values.');
            }
        }
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        if ($normalized === 'payload' || str_ends_with($normalized, '_payload')) {
            return true;
        }

        $identifiesFile = str_contains($normalized, 'file') || str_contains($normalized, 'attachment');
        $identifiesBytes = str_contains($normalized, 'content')
            || str_contains($normalized, 'byte')
            || str_contains($normalized, 'binary')
            || str_ends_with($normalized, '_data');

        return $identifiesFile && $identifiesBytes;
    }
}
