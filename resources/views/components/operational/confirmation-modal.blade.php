@props(['id', 'title', 'description' => null])

<div id="{{ $id }}" class="hs-overlay pointer-events-none fixed inset-0 z-[70] hidden overflow-y-auto bg-slate-950/40" role="dialog" tabindex="-1" aria-modal="true" aria-labelledby="{{ $id }}-title">
    <div class="m-3 opacity-0 transition-all hs-overlay-open:mt-16 hs-overlay-open:opacity-100 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="pointer-events-auto rounded-xl bg-white p-6 shadow-xl">
            <h2 id="{{ $id }}-title" class="text-lg font-bold text-slate-950">{{ $title }}</h2>
            @if ($description)<p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>@endif
            <div class="mt-6">{{ $slot }}</div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-hs-overlay="#{{ $id }}" class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annulla</button>
                @isset($actions)
                    {{ $actions }}
                @endisset
            </div>
        </div>
    </div>
</div>
