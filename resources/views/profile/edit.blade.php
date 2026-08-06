@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb page-title="My profile" />
    <div class="grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
        <section class="h-fit overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="h-24 bg-brand-500/10 dark:bg-brand-500/15"></div>
            <div class="px-5 pb-6 sm:px-6">
                <div class="-mt-10 flex h-20 w-20 items-center justify-center rounded-full border-4 border-white bg-brand-50 text-xl font-semibold text-brand-600 dark:border-gray-900 dark:bg-brand-500/15 dark:text-brand-400">{{ collect(explode(' ', auth()->user()->name))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') }}</div>
                <h2 class="mt-4 text-lg font-semibold text-gray-800 dark:text-white/90">{{ auth()->user()->name }}</h2>
                <p class="mt-1 break-all text-theme-sm text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Security</h2>
            <p class="mt-1 text-theme-sm text-gray-500 dark:text-gray-400">Change the password for this account.</p>
            <form method="POST" action="{{ route('profile.password.update') }}" class="mt-6 space-y-5">@csrf @method('PUT')
                @foreach ([['current_password', 'Current password', 'current-password'], ['password', 'New password', 'new-password'], ['password_confirmation', 'Confirm new password', 'new-password']] as [$name, $label, $autocomplete])
                    <div><label for="{{ $name }}" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-400">{{ $label }}</label><input id="{{ $name }}" name="{{ $name }}" type="password" required autocomplete="{{ $autocomplete }}" class="h-11 w-full rounded-lg border @error($name) border-error-500 @else border-gray-300 @enderror bg-transparent px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">@error($name)<p class="mt-1.5 text-theme-xs text-error-600">{{ $message }}</p>@enderror</div>
                @endforeach
                <x-ui.button type="submit">Update password</x-ui.button>
            </form>
        </section>
    </div>
@endsection
