<?php

namespace App\Domain\Budget\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ApprovalEffectiveDateValidator
{
    public function validate(string $effectiveDate, string $tenantTimezone): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $effectiveDate, $tenantTimezone);
        $errors = CarbonImmutable::getLastErrors();
        if (! $parsed instanceof CarbonImmutable
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $parsed->format('Y-m-d') !== $effectiveDate
            || $effectiveDate > $this->today($tenantTimezone)) {
            throw ValidationException::withMessages([
                'effective_date' => ['La data di efficacia non può essere futura nel fuso del Tenant.'],
            ]);
        }

        return $parsed;
    }

    public function today(string $tenantTimezone): string
    {
        return CarbonImmutable::now($tenantTimezone)->toDateString();
    }
}
