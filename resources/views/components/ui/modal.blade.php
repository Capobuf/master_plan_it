@props(['name', 'title' => 'Conferma azione'])
<div x-data="{ open: false }" x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true" x-on:keydown.escape.window="open = false">
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-900/50 p-4" style="display:none" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div @click.outside="open = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-theme-xl dark:bg-gray-900"><div class="flex items-start justify-between gap-4"><h2 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $title }}</h2><button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600" aria-label="Chiudi">×</button></div><div class="mt-4">{{ $slot }}</div></div>
    </div>
</div>
