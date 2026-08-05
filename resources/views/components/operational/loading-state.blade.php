@props(['label' => 'Loading operational data'])

<div data-state="loading" role="status" aria-live="polite" aria-busy="true" class="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-sm font-medium text-blue-900">
    <span aria-hidden="true" class="size-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>
    {{ $label }}
</div>
