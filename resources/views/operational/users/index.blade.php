@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb pageTitle="Utenti" />
@if(session('success'))<div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700">{{ session('success') }}</div>@endif
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between"><form method="GET" class="flex gap-3"><input class="h-11 rounded-lg border border-gray-300 bg-white px-4 text-sm dark:border-gray-700 dark:bg-gray-800" name="q" value="{{ $filters['q'] }}" placeholder="Cerca nome o email"><x-ui.button type="submit" variant="secondary">Cerca</x-ui.button></form>@if($abilities['create'])<x-ui.button :href="route('operational.users.create')">Aggiungi utente</x-ui.button>@endif</div>
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[.03]"><div class="overflow-x-auto"><table class="min-w-full"><thead class="border-b border-gray-100 dark:border-gray-800"><tr class="text-left text-xs text-gray-500"><th class="px-6 py-3">Utente</th><th class="px-6 py-3">Ruoli</th><th class="px-6 py-3">Stato</th><th class="px-6 py-3 text-right">Azioni</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
@forelse($users['data'] as $user)
    <tr><td class="px-6 py-4"><p class="font-medium text-gray-800 dark:text-white">{{ $user['name'] }}</p><p class="text-sm text-gray-500">{{ $user['email'] }}</p></td><td class="px-6 py-4 text-sm text-gray-500">{{ implode(', ', $user['roles']) ?: '—' }}</td><td class="px-6 py-4"><x-ui.badge :color="$user['isActive'] ? 'success' : 'gray'">{{ $user['isActive'] ? 'Attivo' : 'Inattivo' }}</x-ui.badge></td><td class="px-6 py-4 text-right">@if($abilities['update'])<a href="{{ route('operational.users.edit',$user['id']) }}" class="text-sm font-medium text-brand-500">Modifica</a>@endif</td></tr>
@empty
    <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">Nessun utente trovato.</td></tr>
@endforelse
</tbody></table></div>
@if($users['total'])
    <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4 text-sm text-gray-500 dark:border-gray-800"><span>{{ $users['from'] }}–{{ $users['to'] }} di {{ $users['total'] }}</span><div class="flex gap-1">
        @foreach($users['links'] as $link)
            @if($link['url'])<a href="{{ $link['url'] }}" class="rounded px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-800">{!! $link['label'] !!}</a>@endif
        @endforeach
    </div></div>
@endif
</div>
@endsection
