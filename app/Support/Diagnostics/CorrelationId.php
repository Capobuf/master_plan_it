<?php

namespace App\Support\Diagnostics;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stringable;

final readonly class CorrelationId implements Stringable
{
    public const string HEADER = 'X-Correlation-ID';

    public const string LOG_CONTEXT_KEY = 'correlation_id';

    private function __construct(private string $value) {}

    public static function resolveFor(Request $request): self
    {
        $existing = $request->attributes->get(self::class);

        if ($existing instanceof self) {
            return $existing;
        }

        $candidate = $request->headers->get(self::HEADER);

        if (! is_string($candidate) || ! Str::isUuid($candidate, 4)) {
            $candidate = strtolower((string) Str::uuid());
        } else {
            $candidate = strtolower($candidate);
        }

        $correlationId = new self($candidate);
        $request->attributes->set(self::class, $correlationId);

        return $correlationId;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
