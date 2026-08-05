@props(['code' => 'UNEXPECTED_ERROR', 'correlationId' => null, 'reloadHref' => null, 'retryLabel' => null])

@php
    $message = match ($code) {
        'PERMISSION_DENIED' => 'This item is unavailable.',
        'STALE_VERSION' => 'This item changed before your request could be applied. Your input is still available; reload or re-execute deliberately.',
        default => 'Something went wrong. Please try again.',
    };
@endphp

<section data-state="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-950 shadow-sm">
    <p class="text-xs font-bold uppercase tracking-wide">{{ $code }}</p>
    <p class="mt-1">{{ $message }}</p>
    @if ($correlationId)
        <p class="mt-1">Correlation ID: {{ $correlationId }}</p>
    @endif
    @if ($code === 'STALE_VERSION' && $reloadHref)
        <a href="{{ $reloadHref }}" class="mt-3 inline-flex rounded border border-red-700 px-3 py-2 font-medium">Reload</a>
    @endif
    @if ($retryLabel)
        <button type="button" class="mt-3 rounded border border-red-700 px-3 py-2 font-medium">{{ $retryLabel }}</button>
    @endif
</section>
