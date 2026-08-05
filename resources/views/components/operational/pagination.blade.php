@props(['page', 'lastPage', 'total', 'label' => 'risultati', 'previousAction' => 'previousPage', 'nextAction' => 'nextPage'])

<nav aria-label="{{ $attributes->get('aria-label', 'Paginazione') }}" {{ $attributes->except('aria-label')->class('flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-xs') }}>
    <p class="min-w-0 text-slate-500"><span class="font-semibold text-slate-700">{{ $total }}</span> {{ $label }}</p>
    <div class="flex shrink-0 gap-2">
        <button type="button" wire:click="{{ $previousAction }}" wire:loading.attr="disabled" @disabled($page <= 1) class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white font-semibold text-slate-600 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Pagina precedente">‹</button>
        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-blue-600 font-semibold text-white" aria-current="page">{{ $page }}</span>
        <button type="button" wire:click="{{ $nextAction }}" wire:loading.attr="disabled" @disabled($page >= $lastPage) class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200 bg-white font-semibold text-slate-600 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Pagina successiva">›</button>
    </div>
</nav>
