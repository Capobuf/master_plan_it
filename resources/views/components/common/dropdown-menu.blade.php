@props(['label' => 'Azioni', 'icon' => null])
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300" :aria-expanded="open" aria-haspopup="menu">@if($icon)<x-ui.icon :name="$icon" />@endif{{ $label }}<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg></button>
    <div x-show="open" x-transition class="absolute right-0 z-50 mt-2 min-w-44 rounded-xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900" style="display:none" role="menu">{{ $slot }}</div>
</div>
