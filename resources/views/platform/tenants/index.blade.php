@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb page-title="Tenants" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-theme-sm text-gray-500 dark:text-gray-400">Manage platform tenant workspaces.</p>
        @if ($abilities['create'] ?? false)
            <a href="{{ route('platform.tenants.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3.5 text-theme-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Add tenant</a>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="max-w-full overflow-x-auto custom-scrollbar">
            <table class="min-w-full">
                <thead class="border-b border-gray-100 dark:border-gray-800">
                    <tr>
                        @foreach (['Tenant', 'Code', 'Currency', 'Language', 'Status', 'Actions'] as $heading)
                            <th class="px-5 py-3 text-left sm:px-6"><span class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">{{ $heading }}</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($tenants as $tenant)
                        <tr>
                            <td class="px-5 py-4 sm:px-6"><p class="font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $tenant['name'] }}</p><p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ $tenant['timezone'] }}</p></td>
                            <td class="px-5 py-4 text-theme-sm text-gray-500 dark:text-gray-400">{{ $tenant['code'] }}</td>
                            <td class="px-5 py-4 text-theme-sm text-gray-500 dark:text-gray-400">{{ $tenant['currency'] }}</td>
                            <td class="px-5 py-4 text-theme-sm text-gray-500 dark:text-gray-400">{{ $tenant['language'] }}</td>
                            <td class="px-5 py-4"><x-ui.badge :color="$tenant['state'] === 'active' ? 'success' : 'light'">{{ $tenant['state'] }}</x-ui.badge></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap items-center gap-3 text-theme-sm font-medium">
                                    @if (($abilities['enter'] ?? false) && $tenant['state'] === 'active')
                                        <form method="POST" action="{{ route('platform.tenants.enter', $tenant['id']) }}">@csrf<button data-enter-tenant="{{ $tenant['id'] }}" class="text-brand-500 hover:text-brand-600">Enter</button></form>
                                    @endif
                                    @if ($abilities['update'] ?? false)<a class="text-brand-500 hover:text-brand-600" href="{{ route('platform.tenants.edit', $tenant['id']) }}">Edit</a>@endif
                                    @if (($abilities['deactivate'] ?? false) && $tenant['state'] === 'active')
                                        <button type="button" @click="$dispatch('open-modal', 'deactivate-tenant-{{ $tenant['id'] }}')" class="text-error-600 hover:text-error-700">Deactivate</button>
                                    @endif
                                    @if (($abilities['reactivate'] ?? false) && $tenant['state'] === 'inactive')
                                        <form method="POST" action="{{ route('platform.tenants.reactivate', $tenant['id']) }}">@csrf<input type="hidden" name="lock_version" value="{{ $tenant['lockVersion'] }}"><button class="text-success-600 hover:text-success-700">Reactivate</button></form>
                                    @endif
                                </div>
                                @if (($abilities['deactivate'] ?? false) && $tenant['state'] === 'active')
                                    <x-ui.modal name="deactivate-tenant-{{ $tenant['id'] }}" title="Deactivate tenant">
                                        <form method="POST" action="{{ route('platform.tenants.deactivate', $tenant['id']) }}" class="p-6 sm:p-8">@csrf
                                            <input type="hidden" name="lock_version" value="{{ $tenant['lockVersion'] }}">
                                            <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Deactivate {{ $tenant['name'] }}</h3>
                                            <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400">Type <strong>{{ $tenant['code'] }}</strong> to confirm. Tenant access will stop.</p>
                                            <label class="mt-5 block text-theme-sm font-medium text-gray-700 dark:text-gray-400" for="tenant-code-{{ $tenant['id'] }}">Tenant code</label>
                                            <input id="tenant-code-{{ $tenant['id'] }}" name="confirmation_code" required autocomplete="off" class="mt-1.5 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <div class="mt-6 flex justify-end"><x-ui.button type="submit" class-name="bg-error-600 hover:bg-error-700">Deactivate</x-ui.button></div>
                                        </form>
                                    </x-ui.modal>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-theme-sm text-gray-500 dark:text-gray-400">No tenants yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
