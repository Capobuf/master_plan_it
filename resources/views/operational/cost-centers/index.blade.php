@extends('layouts.app')
@section('content')
<x-common.page-breadcrumb pageTitle="Centri di costo" />
@if(session('success'))<div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700">{{ session('success') }}</div>@endif
<div class="mb-6 flex justify-end">@if($abilities['create'])<x-ui.button :href="route('operational.cost-centers.create')">Aggiungi centro</x-ui.button>@endif</div>
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[.03]"><table class="min-w-full"><thead class="border-b border-gray-100 dark:border-gray-800"><tr class="text-left text-xs text-gray-500"><th class="px-6 py-3">Centro</th><th class="px-6 py-3">Stato</th><th class="px-6 py-3 text-right">Azioni</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse($costCenters as $center)@include('operational.cost-centers._row', ['center' => $center])@empty<tr><td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">Nessun centro di costo.</td></tr>@endforelse</tbody></table></div>
@endsection
