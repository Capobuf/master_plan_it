<section aria-label="Registro spese correnti" aria-busy="false" wire:loading.attr="aria-busy">
    <x-operational.breadcrumb :items="[
        ['label' => 'Panoramica', 'href' => route('operational.index')],
        ['label' => 'Spese'],
        ['label' => 'Registro spese'],
    ]" />

    <x-operational.page-header class="mt-5" title="Registro spese" description="Consulta le spese correnti del tenant e i totali calcolati dal server.">
        <x-slot:badge><x-operational.status-badge tone="info">Dataset corrente</x-operational.status-badge></x-slot:badge>
        <x-slot:actions>
            <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2Z"/><path d="M8 8h8m-8 4h8"/></svg>
                {{ $total }} {{ $total === 1 ? 'spesa' : 'spese' }}
            </span>
            <x-operational.status-badge tone="neutral" class="rounded-lg px-3 py-2 text-xs normal-case tracking-normal">Sola lettura</x-operational.status-badge>
        </x-slot:actions>
    </x-operational.page-header>

    <div class="mt-6 grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="min-w-0 space-y-4">
            <x-operational.filter-panel wire:submit="applyFilters">
                <fieldset wire:loading.attr="disabled" class="flex flex-col gap-4 disabled:cursor-wait disabled:opacity-60 lg:flex-row lg:items-end">
                    <label class="block min-w-0 flex-1 text-xs font-semibold text-slate-600">
                        Anno di pianificazione
                        <select wire:model="planningYearId" class="mt-1.5 block w-full rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-800 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Tutti gli anni</option>
                            @foreach ($years as $year)
                                <option value="{{ $year['id'] }}">{{ $year['label'] }}{{ $year['active'] ? '' : ' · inattivo' }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="hidden flex-1 lg:block">
                        <p class="text-xs font-semibold text-slate-600">Centro di costo</p>
                        <div class="mt-1.5 flex h-10.5 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-400" aria-disabled="true">Tutti · filtro in arrivo</div>
                    </div>

                    <div class="hidden flex-1 lg:block">
                        <p class="text-xs font-semibold text-slate-600">Tipologia</p>
                        <div class="mt-1.5 flex h-10.5 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-400" aria-disabled="true">Tutte · filtro in arrivo</div>
                    </div>

                    <button type="submit" class="inline-flex h-10.5 items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16M7 12h10m-7 7h4"/></svg>
                        Applica
                    </button>
                </fieldset>
            </x-operational.filter-panel>

            <div class="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50/70 px-4 py-3 text-xs text-blue-800">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></svg>
                <p><span class="font-semibold">Vista corrente:</span> sono escluse revisioni storiche, righe eliminate e dati di altri tenant.</p>
            </div>

            <div wire:loading.flex class="flex-col gap-3">
                <x-operational.loading-state label="Caricamento delle spese correnti" />
                <div aria-hidden="true" class="animate-pulse space-y-2 rounded-xl border border-slate-200 bg-white p-4">
                    <div class="h-9 rounded bg-slate-100"></div>
                    <div class="h-12 rounded bg-slate-100"></div>
                    <div class="h-12 rounded bg-slate-100"></div>
                    <div class="h-12 rounded bg-slate-100"></div>
                </div>
            </div>

            @if ($errorCode)
                <x-operational.error-state :code="$errorCode" :correlation-id="$correlationId" />
                @if ($errorCode === 'UNEXPECTED_ERROR')
                    <button type="button" wire:click="reloadRegister" wire:loading.attr="disabled" class="inline-flex rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-800 shadow-sm disabled:opacity-60">Riprova</button>
                @endif
            @elseif ($rows === [])
                <x-operational.empty-state title="Nessuna spesa corrente" description="Non ci sono spese correnti per il tenant e l'anno selezionati. Prova un altro anno quando disponibile." />
            @else
                <x-operational.table-container>
                    <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <p>Mostra fino a <span class="font-semibold text-slate-700">15</span> righe per pagina</p>
                        <p><span class="font-semibold text-slate-700">{{ $total }}</span> risultati · pagina {{ $page }} di {{ $lastPage }}</p>
                    </div>
                    <div class="operational-table-scroll overflow-x-auto">
                        <table class="w-full divide-y divide-slate-200 text-xs">
                            <thead class="bg-slate-50 text-left text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="hidden px-4 py-3 sm:table-cell">ID</th>
                                    <th scope="col" class="px-4 py-3">Spesa</th>
                                    <th scope="col" class="hidden px-4 py-3 md:table-cell">Anno</th>
                                    <th scope="col" class="hidden px-4 py-3 md:table-cell">Centro di costo</th>
                                    <th scope="col" class="hidden px-4 py-3 lg:table-cell">Tipologia</th>
                                    <th scope="col" class="hidden px-4 py-3 text-right lg:table-cell">Righe</th>
                                    <th scope="col" class="hidden px-4 py-3 text-right xl:table-cell">Netto</th>
                                    <th scope="col" class="px-4 py-3 text-right">Totale</th>
                                    <th scope="col" class="w-12 px-3 py-3"><span class="sr-only">Azioni</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @foreach ($rows as $row)
                                    <tr wire:key="expense-register-{{ $row['id'] }}" class="transition hover:bg-blue-50/40">
                                        <td class="hidden whitespace-nowrap px-4 py-3 font-mono text-[11px] font-semibold text-slate-500 sm:table-cell">#{{ $row['id'] }}</td>
                                        <td class="max-w-72 px-4 py-3">
                                            <a href="{{ route('operational.expenses.show', ['expense' => $row['id']]) }}" class="block truncate font-semibold text-slate-900 hover:text-blue-700 hover:underline">{{ $row['title'] }}</a>
                                        </td>
                                        <td class="hidden whitespace-nowrap px-4 py-3 md:table-cell">{{ $row['year'] }}</td>
                                        <td class="hidden max-w-52 px-4 py-3 md:table-cell"><span class="block truncate">{{ $row['cost_center'] }}</span></td>
                                        <td class="hidden whitespace-nowrap px-4 py-3 lg:table-cell"><x-operational.status-badge tone="info">{{ $row['kind'] }}</x-operational.status-badge></td>
                                        <td class="hidden px-4 py-3 text-right font-mono lg:table-cell">{{ $row['row_count'] }}</td>
                                        <td class="hidden whitespace-nowrap px-4 py-3 text-right xl:table-cell"><x-operational.money :amount="$row['net']" /></td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900"><x-operational.money :amount="$row['gross']" /></td>
                                        <td class="px-3 py-3 text-right"><a href="{{ route('operational.expenses.show', ['expense' => $row['id']]) }}" aria-label="Apri {{ $row['title'] }}" class="inline-flex size-8 items-center justify-center rounded-lg text-slate-500 hover:bg-blue-100 hover:text-blue-700">→</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-operational.pagination :page="$page" :last-page="$lastPage" :total="$total" label="spese correnti" aria-label="Paginazione registro spese" />
                </x-operational.table-container>
            @endif
        </div>

        <aside class="space-y-4" aria-label="Riepilogo spese">
            <x-operational.card class="p-5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Riepilogo spese</p>
                <p class="mt-1 text-xs text-slate-500">Filtro corrente</p>
                <dl class="mt-5 space-y-4">
                    <div class="border-b border-slate-100 pb-4">
                        <dt class="text-xs text-slate-500">Totale lordo</dt>
                        <dd class="mt-1 text-2xl font-bold tracking-tight text-slate-950"><x-operational.money :amount="$totals['gross']" /></dd>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Netto</dt><dd class="mt-1 text-sm font-semibold text-slate-800"><x-operational.money :amount="$totals['net']" /></dd></div>
                        <div><dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">IVA</dt><dd class="mt-1 text-sm font-semibold text-slate-800"><x-operational.money :amount="$totals['vat']" /></dd></div>
                    </div>
                </dl>
            </x-operational.card>

            <x-operational.card class="p-5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Dataset</p>
                <ul class="mt-4 space-y-3 text-xs">
                    <li class="flex items-center justify-between gap-3"><span class="text-slate-500">Stato</span><x-operational.status-badge tone="success">Corrente</x-operational.status-badge></li>
                    <li class="flex items-center justify-between gap-3"><span class="text-slate-500">Spese</span><span class="font-mono font-semibold text-slate-800">{{ $total }}</span></li>
                    <li class="flex items-center justify-between gap-3"><span class="text-slate-500">Storico</span><span class="font-semibold text-slate-400">Escluso</span></li>
                </ul>
            </x-operational.card>
        </aside>
    </div>
</section>
