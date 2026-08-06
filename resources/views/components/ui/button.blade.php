@props(['href' => null, 'variant' => 'primary', 'type' => 'button', 'className' => '', 'disabled' => false])
@php
    $variantClass = in_array($variant, ['outline', 'secondary'], true)
        ? 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300'
        : 'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300';
    $classes = trim("inline-flex items-center justify-center gap-2 rounded-lg px-5 py-3.5 text-sm font-medium transition {$variantClass} {$className}");
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
