@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb pageTitle="Ruoli" />
@if(session('success'))<div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700">{{ session('success') }}</div>@endif
<div class="mb-6 flex justify-end">@if($abilities['create'])<x-ui.button :href="route('operational.roles.create')">Aggiungi ruolo</x-ui.button>@endif</div>
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[.03]"><table class="min-w-full"><thead class="border-b border-gray-100 dark:border-gray-800"><tr class="text-left text-xs text-gray-500"><th class="px-6 py-3">Ruolo</th><th class="px-6 py-3">Abilità</th><th class="px-6 py-3 text-right">Azioni</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
@forelse($roles as $role)
    <tr><td class="px-6 py-4 font-medium text-gray-800 dark:text-white">{{ $role['name'] }}</td><td class="px-6 py-4 text-sm text-gray-500">{{ count($role['abilities']) }}</td><td class="px-6 py-4 text-right"><div class="flex justify-end gap-3 text-sm font-medium">
        @if($abilities['update'])<a href="{{ route('operational.roles.edit',$role['id']) }}" class="text-brand-500">Modifica</a>@endif
        @if($abilities['delete'])<form method="POST" action="{{ route('operational.roles.destroy',$role['id']) }}">@csrf @method('DELETE')<button class="text-error-500">Elimina</button></form>@endif
    </div></td></tr>
@empty
    <tr><td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">Nessun ruolo configurato.</td></tr>
@endforelse
</tbody></table></div>
@endsection
