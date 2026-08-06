@extends('layouts.app')

@section('content')
    @php($pageTitle = 'Fornitori')
    <x-common.page-breadcrumb :pageTitle="$pageTitle" />

    @if(session('success'))
        <div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/15 dark:text-success-400" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <form method="GET" action="{{ route('operational.vendors.index') }}" class="grid flex-1 gap-4 sm:grid-cols-[minmax(0,1fr)_180px_auto]">
            <div>
                <label for="q" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Cerca</label>
                <input id="q" name="q" value="{{ $filters['q'] }}" type="search" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-hidden placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="Nome fornitore">
            </div>
            <div>
                <label for="status" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Stato</label>
                <select id="status" name="status" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="all" @selected($filters['status'] === 'all')>Tutti</option>
                    <option value="active" @selected($filters['status'] === 'active')>Attivi</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inattivi</option>
                </select>
            </div>
            <x-ui.button type="submit" variant="outline">Filtra</x-ui.button>
        </form>
        @if($abilities['create'])
            <a href="{{ route('operational.vendors.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 py-3.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Aggiungi fornitore</a>
        @endif
    </div>

    @if(count($vendors['data']))
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="{ open: false, action: '', method: 'POST', name: '', lockVersion: 0, title: '' }">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="border-b border-gray-100 dark:border-gray-800">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Fornitore</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Partita IVA</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Contatti</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Stato</th>
                            <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400"><span class="sr-only">Azioni</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($vendors['data'] as $vendor)
                            <tr>
                                <td class="px-5 py-4 text-sm font-medium text-gray-800 dark:text-white/90">{{ $vendor['name'] }}</td>
                                <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $vendor['vatNumber'] ?: '—' }}</td>
                                <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400"><div>{{ $vendor['email'] ?: '—' }}</div><div class="mt-1 text-xs">{{ $vendor['phone'] ?: '' }}</div></td>
                                <td class="px-5 py-4"><x-ui.badge :color="$vendor['active'] ? 'success' : 'light'">{{ $vendor['active'] ? 'Attivo' : 'Inattivo' }}</x-ui.badge></td>
                                <td class="px-5 py-4 text-right">
                                    <div class="inline-flex flex-wrap justify-end gap-3 text-sm font-medium">
                                        @if($abilities['update']) <a class="text-brand-500 hover:text-brand-600" href="{{ route('operational.vendors.edit', $vendor['id']) }}">Modifica</a> @endif
                                        @if($abilities['viewRevisions']) <a class="text-brand-500 hover:text-brand-600" href="{{ route('operational.vendors.history', $vendor['id']) }}">Storico</a> @endif
                                        @if($vendor['active'] && $abilities['deactivate'])
                                            <button type="button" class="text-warning-600 hover:text-warning-700" @click="open = true; action = '{{ route('operational.vendors.deactivate', $vendor['id']) }}'; method = 'POST'; name = @js($vendor['name']); lockVersion = {{ $vendor['lockVersion'] }}; title = 'Disattiva fornitore'">Disattiva</button>
                                        @elseif(!$vendor['active'] && $abilities['reactivate'])
                                            <button type="button" class="text-success-600 hover:text-success-700" @click="open = true; action = '{{ route('operational.vendors.reactivate', $vendor['id']) }}'; method = 'POST'; name = @js($vendor['name']); lockVersion = {{ $vendor['lockVersion'] }}; title = 'Riattiva fornitore'">Riattiva</button>
                                        @endif
                                        @if($abilities['delete'])
                                            <button type="button" class="text-error-500 hover:text-error-600" @click="open = true; action = '{{ route('operational.vendors.destroy', $vendor['id']) }}'; method = 'DELETE'; name = @js($vendor['name']); lockVersion = {{ $vendor['lockVersion'] }}; title = 'Elimina fornitore'">Elimina</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400 sm:flex-row sm:items-center sm:justify-between">
                <p>{{ $vendors['from'] }}–{{ $vendors['to'] }} di {{ $vendors['total'] }}</p>
                <nav class="flex items-center gap-1" aria-label="Paginazione">
                    @foreach($vendors['links'] as $link)
                        @if($link['url']) <a href="{{ $link['url'] }}" class="rounded-lg px-3 py-2 hover:bg-gray-100 dark:hover:bg-white/[0.05]">{!! $link['label'] !!}</a>
                        @else <span @class(['rounded-lg px-3 py-2', 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' => $link['active'], 'text-gray-400' => !$link['active']])>{!! $link['label'] !!}</span> @endif
                    @endforeach
                </nav>
            </div>
            <div x-cloak x-show="open" x-transition class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-900/50 p-4" @keydown.escape.window="open = false" role="dialog" aria-modal="true">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900" @click.outside="open = false">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="title"></h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Confermi l'operazione per <span class="font-medium text-gray-800 dark:text-white/90" x-text="name"></span>?</p>
                    <form method="POST" :action="action" class="mt-6 flex justify-end gap-3">
                        @csrf <input type="hidden" name="lock_version" :value="lockVersion"><template x-if="method === 'DELETE'"><input type="hidden" name="_method" value="DELETE"></template>
                        <x-ui.button type="button" variant="outline" @click="open = false">Annulla</x-ui.button>
                        <x-ui.button type="submit">Conferma</x-ui.button>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-700 dark:bg-white/[0.03]"><h2 class="text-base font-medium text-gray-800 dark:text-white/90">Nessun fornitore trovato</h2><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Aggiungi un fornitore quando deve essere gestito nell'anagrafica.</p></div>
    @endif
@endsection
