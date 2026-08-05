@props(['tone' => 'neutral'])

@php
    $tones = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'danger' => 'bg-red-50 text-red-700 ring-red-200',
        'info' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'neutral' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset', $tones[$tone] ?? $tones['neutral']]) }}>{{ $slot }}</span>
