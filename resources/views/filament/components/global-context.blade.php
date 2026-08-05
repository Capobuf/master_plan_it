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
        @if ($indicator->isSelected() && auth()->user()?->can('dashboard.view'))
            <a href="{{ route('operational.index') }}" class="mt-2 inline-flex rounded text-sm font-medium text-primary-700 underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-700 dark:text-primary-300">
                Open operational workspace
            </a>
        @endif
    </div>
@endif
