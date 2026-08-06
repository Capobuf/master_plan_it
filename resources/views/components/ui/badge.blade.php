@props(['color' => 'gray'])
@php($colors = ['gray'=>'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300','success'=>'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400','warning'=>'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400','error'=>'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400'])
<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium '.($colors[$color] ?? $colors['gray'])]) }}>{{ $slot }}</span>
