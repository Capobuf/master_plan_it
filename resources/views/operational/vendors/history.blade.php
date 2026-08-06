@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="'Storico · '.$vendor['name']" />
    @if(session('success')) <div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/30 dark:bg-success-500/15 dark:text-success-400" role="status">{{ session('success') }}</div> @endif
    @if(count($history))
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="{ open: false, revision: null }">
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($history as $item)
                    <li class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="text-sm font-semibold capitalize text-gray-800 dark:text-white/90">{{ $item['operation'] }}</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item['actor'] ?: 'Sistema' }} · {{ $item['timestamp'] ?: '—' }}</p><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Revisione sorgente: {{ $item['sourceRevisionId'] ?: '—' }}</p>@if($item['reason'])<p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $item['reason'] }}</p>@endif</div>@if($canRestore && $item['sourceRevisionId'])<button type="button" class="text-sm font-medium text-brand-500 hover:text-brand-600" @click="open = true; revision = {{ $item['sourceRevisionId'] }}">Ripristina</button>@endif</li>
                @endforeach
            </ul>
            <div x-cloak x-show="open" class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-900/50 p-4" @keydown.escape.window="open = false"><div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900" @click.outside="open = false"><h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Ripristina revisione</h2><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Ripristinare questa revisione del fornitore {{ $vendor['name'] }}?</p><form method="POST" :action="'{{ route('operational.vendors.history.restore', ['vendor' => $vendor['id'], 'version' => '__revision__']) }}'.replace('__revision__', revision)" class="mt-6 flex justify-end gap-3">@csrf<input type="hidden" name="lock_version" value="{{ $vendor['lockVersion'] }}"><x-ui.button type="button" variant="outline" @click="open = false">Annulla</x-ui.button><x-ui.button type="submit">Ripristina</x-ui.button></form></div></div>
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-700 dark:bg-white/[0.03]"><h2 class="text-base font-medium text-gray-800 dark:text-white/90">Nessuna revisione</h2><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Non sono state registrate modifiche per questo fornitore.</p></div>
    @endif
@endsection
