@php($editing = isset($record))
<form method="POST" action="{{ $editing ? route('platform.tenants.update', $record['id']) : route('platform.tenants.store') }}" class="max-w-4xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
    @csrf
    @if ($editing) @method('PUT') <input type="hidden" name="lock_version" value="{{ $record['lockVersion'] }}"> @endif
    <div class="grid gap-5 sm:grid-cols-2">
        @foreach ([
            ['name', 'Name', $record['name'] ?? '', 'text'],
            ['code', 'Code', $record['code'] ?? '', 'text'],
            ['currency_code', 'Currency code', $record['currency'] ?? '', 'text'],
            ['language_code', 'Language code', $record['language'] ?? '', 'text'],
            ['timezone', 'Timezone', $record['timezone'] ?? '', 'text'],
            ['default_vat_rate', 'Default VAT rate', $record['defaultVatRate'] ?? '', 'text'],
        ] as [$name, $label, $value, $type])
            <div>
                <label for="{{ $name }}" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-400">{{ $label }}</label>
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" required value="{{ old($name, $value) }}" class="h-11 w-full rounded-lg border @error($name) border-error-500 @else border-gray-300 @enderror bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                @error($name)<p class="mt-1.5 text-theme-xs text-error-600">{{ $message }}</p>@enderror
            </div>
        @endforeach
    </div>
    <div class="mt-6"><x-ui.button type="submit">{{ $editing ? 'Save changes' : 'Create tenant' }}</x-ui.button></div>
</form>
