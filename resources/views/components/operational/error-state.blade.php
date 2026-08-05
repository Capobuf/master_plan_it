@props(['code' => 'UNEXPECTED_ERROR', 'correlationId' => null, 'reloadHref' => null])

@php
    $message = match ($code) {
        'PERMISSION_DENIED' => 'Non disponi dell’autorizzazione necessaria per questo elemento.',
        'STALE_VERSION' => 'Questo elemento è cambiato prima che la richiesta fosse applicata. I dati inseriti restano disponibili: aggiorna e ripeti l’operazione consapevolmente.',
        default => 'Si è verificato un errore. Riprova tra poco.',
    };
@endphp

<section data-state="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-950 shadow-sm">
    <p class="text-xs font-bold uppercase tracking-wide">{{ $code }}</p>
    <p class="mt-1">{{ $message }}</p>
    @if ($correlationId)
        <p class="mt-1">ID correlazione: {{ $correlationId }}</p>
    @endif
    @if ($code === 'STALE_VERSION' && $reloadHref)
        <a href="{{ $reloadHref }}" class="mt-3 inline-flex rounded border border-red-700 px-3 py-2 font-medium">Aggiorna</a>
    @endif
</section>
