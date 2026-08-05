@props(['amount', 'currency' => null])

@php
    $currency ??= request()->attributes
        ->get(\App\Domain\Tenancy\Data\TenantContext::class)
        ?->currencyCode ?? 'EUR';
@endphp

<span {{ $attributes->class('whitespace-nowrap tabular-nums no-underline') }}>{{ \App\Support\Formatting\MoneyFormatter::format((string) $amount, (string) $currency) }}</span>
