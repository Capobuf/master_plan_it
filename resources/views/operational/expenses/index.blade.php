@extends('layouts.app')

@section('title', 'Spese')
@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm text-gray-500">Operatività</p><h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Registro spese</h1></div>
        @if($abilities['create'])<a href="{{ route('operational.expenses.create') }}" class="inline-flex items-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">Nuova spesa</a>@endif
    </div>
    <form method="GET" class="mb-6 max-w-xs"><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Anno di pianificazione</label><select name="year" onchange="this.form.submit()" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:bg-gray-900"><option value="">Tutti gli anni</option>@foreach($yearOptions as $year)<option value="{{ $year['value'] }}" @selected($selectedYear === $year['value'])>{{ $year['label'] }}{{ $year['active'] ? ' · Attivo' : '' }}</option>@endforeach</select></form>
    <div class="mb-6 grid gap-4 sm:grid-cols-3">@foreach(['Netto'=>$totals['net'], 'IVA'=>$totals['vat'], 'Lordo'=>$totals['gross']] as $label => $amount)<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-xl font-semibold text-gray-800 dark:text-white">{{ $amount }}</p></div>@endforeach</div>
    @if(count($expenses['data']))
    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"><table class="min-w-full text-left text-sm"><thead class="border-b border-gray-100 text-xs uppercase text-gray-500 dark:border-gray-800"><tr><th class="px-5 py-4">Anno</th><th class="px-5 py-4">Spesa</th><th class="px-5 py-4">Centro di costo</th><th class="px-5 py-4">Righe</th><th class="px-5 py-4">Netto</th><th class="px-5 py-4">IVA</th><th class="px-5 py-4">Lordo</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @foreach($expenses['data'] as $expense)
            <tr><td class="px-5 py-4">{{ $expense['planningYearLabel'] }}</td><td class="px-5 py-4"><a class="font-medium text-gray-800 hover:text-brand-500 dark:text-white" href="{{ route('operational.expenses.show', $expense['id']) }}">{{ $expense['title'] }}</a><p class="mt-1 text-xs capitalize text-gray-500">{{ $expense['kind'] }}</p>
                @if($expense['contractTitle'])
                    @if($expense['contractHref'])
                        <a class="mt-1 block text-xs text-brand-500" href="{{ $expense['contractHref'] }}">{{ $expense['contractTitle'] }}</a>
                    @else
                        <p class="mt-1 text-xs text-gray-500">{{ $expense['contractTitle'] }}</p>
                    @endif
                @endif
            </td><td class="px-5 py-4">{{ $expense['costCenterName'] }}</td><td class="px-5 py-4">{{ $expense['rowCount'] }}</td><td class="px-5 py-4">{{ $expense['net'] }}</td><td class="px-5 py-4">{{ $expense['vat'] }}</td><td class="px-5 py-4 font-medium text-gray-800 dark:text-white">{{ $expense['gross'] }}</td></tr>
        @endforeach
    </tbody></table></div>
    @if($expenses['lastPage'] > 1)<nav class="mt-5 flex gap-1">@foreach($expenses['links'] as $link)<a class="rounded-lg px-3 py-2 text-sm {{ $link['active'] ? 'bg-brand-500 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400' }} {{ $link['url'] ? '' : 'pointer-events-none opacity-40' }}" href="{{ $link['url'] ?? '#' }}">{!! $link['label'] !!}</a>@endforeach</nav>@endif
    @else<div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-white/[0.03]">Nessuna spesa per i criteri selezionati.</div>@endif
@endsection
