<section aria-label="Dettaglio spesa" aria-busy="false" wire:loading.attr="aria-busy">
    <x-operational.breadcrumb :items="[
        ['label' => 'Panoramica', 'href' => route('operational.index')],
        ['label' => 'Spese', 'href' => route('operational.expenses.index')],
        ['label' => 'Dettaglio'],
    ]" />

    <div class="mt-5 flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('operational.expenses.index') }}" class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-900 hover:underline">
            <span aria-hidden="true">←</span>
            Torna al registro spese
        </a>
        <span class="inline-flex w-fit rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">Vista corrente · sola lettura</span>
    </div>

    <div wire:loading.flex class="mt-6 flex-col gap-3">
        <x-operational.loading-state label="Caricamento dettaglio spesa" />
        <div aria-hidden="true" class="animate-pulse space-y-3">
            <div class="h-36 rounded-xl bg-slate-200"></div>
            <div class="h-64 rounded-xl bg-slate-200"></div>
        </div>
    </div>

    @if ($errorCode)
        <div class="mt-6">
            <x-operational.error-state :code="$errorCode" :correlation-id="$correlationId" />
            <button type="button" wire:click="reloadExpense" wire:loading.attr="disabled" class="mt-3 inline-flex rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-800 shadow-sm disabled:opacity-60">Riprova</button>
        </div>
    @elseif ($detail !== null)
        <div class="mt-6 grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="min-w-0 space-y-5">
                <x-operational.card class="overflow-hidden">
                    <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs font-semibold text-slate-500">#{{ $detail['id'] }}</span>
                                <x-operational.status-badge tone="info">{{ $detail['kind'] }}</x-operational.status-badge>
                                <x-operational.status-badge tone="success">Corrente</x-operational.status-badge>
                            </div>
                            <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $detail['title'] }}</h1>
                            <p class="mt-2 text-sm text-slate-600">Anno {{ $detail['year'] }} · Centro di costo <span class="font-semibold text-slate-800">{{ $detail['cost_center'] }}</span></p>
                        </div>
                        <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2Z"/><path d="M8 8h8m-8 4h8"/></svg>
                        </span>
                    </div>
                    @if ($detail['notes'])
                        <div class="border-t border-slate-100 bg-slate-50/70 px-6 py-4">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Note</p>
                            <p class="mt-1 text-sm leading-6 text-slate-700">{{ $detail['notes'] }}</p>
                        </div>
                    @endif
                </x-operational.card>

                @if ($detail['rows'] === [])
                    <x-operational.empty-state title="Nessuna riga corrente" description="Questa spesa non contiene righe correnti: i totali server sono quindi pari a zero." action-label="Torna al registro spese" :action-href="route('operational.expenses.index')" />
                @else
                    <x-operational.table-container aria-labelledby="expense-lines-title">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div>
                                <h2 id="expense-lines-title" class="text-sm font-bold text-slate-900">Righe della spesa</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ count($detail['rows']) }} {{ count($detail['rows']) === 1 ? 'riga corrente' : 'righe correnti' }}</p>
                            </div>
                        </div>
                        <div class="operational-table-scroll overflow-x-auto">
                            <table class="w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-50 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">Pos.</th>
                                        <th scope="col" class="px-4 py-3">Descrizione</th>
                                        <th scope="col" class="hidden px-4 py-3 md:table-cell">Tipo</th>
                                        <th scope="col" class="hidden px-4 py-3 text-right sm:table-cell">Netto</th>
                                        <th scope="col" class="hidden px-4 py-3 text-right lg:table-cell">IVA</th>
                                        <th scope="col" class="px-4 py-3 text-right">Lordo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @foreach ($detail['rows'] as $row)
                                        <tr wire:key="expense-detail-row-{{ $row['id'] }}" class="transition hover:bg-blue-50/40">
                                            <td class="hidden px-4 py-3 font-mono text-slate-500 sm:table-cell">{{ $row['position'] }}</td>
                                            <td class="max-w-sm px-4 py-3 font-medium text-slate-900">{{ $row['description'] }}</td>
                                            <td class="hidden px-4 py-3 md:table-cell"><x-operational.status-badge>{{ $row['type'] }}</x-operational.status-badge></td>
                                            <td class="hidden whitespace-nowrap px-4 py-3 text-right sm:table-cell"><x-operational.money :amount="$row['net_amount']" /></td>
                                            <td class="hidden whitespace-nowrap px-4 py-3 text-right lg:table-cell"><x-operational.money :amount="$row['vat_amount']" /></td>
                                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900"><x-operational.money :amount="$row['gross_amount']" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-operational.table-container>
                @endif
            </div>

            <aside class="space-y-4" aria-label="Riepilogo spesa">
                <x-operational.card class="p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Riepilogo economico</p>
                    <dl class="mt-5 space-y-4">
                        <div class="border-b border-slate-100 pb-4">
                            <dt class="text-xs text-slate-500">Totale lordo</dt>
                            <dd class="mt-1 text-2xl font-bold tracking-tight text-slate-950"><x-operational.money :amount="$detail['gross']" /></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-xs text-slate-500">Netto</dt><dd class="text-sm font-semibold text-slate-800"><x-operational.money :amount="$detail['net']" /></dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-xs text-slate-500">IVA</dt><dd class="text-sm font-semibold text-slate-800"><x-operational.money :amount="$detail['vat']" /></dd></div>
                    </dl>
                </x-operational.card>

                <x-operational.card class="p-5">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Informazioni</p>
                    <dl class="mt-4 space-y-3 text-xs">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Anno</dt><dd class="font-semibold text-slate-800">{{ $detail['year'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Tipologia</dt><dd class="font-semibold uppercase text-slate-800">{{ $detail['kind'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Righe</dt><dd class="font-mono font-semibold text-slate-800">{{ count($detail['rows']) }}</dd></div>
                        <div class="border-t border-slate-100 pt-3"><dt class="text-slate-500">Centro di costo</dt><dd class="mt-1 font-semibold text-slate-800">{{ $detail['cost_center'] }}</dd></div>
                    </dl>
                </x-operational.card>
            </aside>
        </div>
    @endif
</section>
