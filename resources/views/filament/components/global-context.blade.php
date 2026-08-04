@php
    /** @var \App\Filament\Components\TenantContextIndicator $indicator */
    /** @var 'sidebar'|'breadcrumb' $surface */
@endphp

@if ($surface === 'breadcrumb')
    <nav aria-label="Tenant context" class="min-w-0">
        <span role="status" aria-live="polite" aria-atomic="true" class="block min-w-0 break-words text-sm text-gray-700 dark:text-gray-200">
            {{ $indicator->label() }}
            @if ($indicator->isSelected())
                <span aria-label="Tenant state">{{ $indicator->tenantState() }}</span>
            @endif
        </span>
    </nav>
@else
    <div class="min-w-0 px-3 py-2">
        <span role="status" aria-live="polite" aria-atomic="true" class="block min-w-0 break-words text-sm text-gray-700 dark:text-gray-200">
            {{ $indicator->label() }}
            @if ($indicator->isSelected())
                <span aria-label="Tenant state">{{ $indicator->tenantState() }}</span>
            @endif
        </span>
    </div>
@endif
