@php($editing = isset($user))
@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="$pageTitle" />
@if(session('success'))<div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700">{{ session('success') }}</div>@endif
<form method="POST" action="{{ $editing ? route('operational.users.update', $user['id']) : route('operational.users.store') }}" class="max-w-3xl rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[.03]">
    @csrf
    @if($editing)
        @method('PUT')
    @endif
    <div class="grid gap-5 p-6 sm:grid-cols-2">
        <div><label class="mb-1 block text-sm font-medium" for="name">Nome</label><input id="name" name="name" required value="{{ old('name', $user['name'] ?? '') }}" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700">@error('name')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror</div>
        <div><label class="mb-1 block text-sm font-medium" for="email">Email</label><input id="email" name="email" type="email" required value="{{ old('email', $user['email'] ?? '') }}" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700">@error('email')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror</div>
        @if(!$editing)
            <div class="sm:col-span-2"><label class="mb-1 block text-sm font-medium" for="password">Password</label><input id="password" name="password" type="password" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700">@error('password')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror</div>
        @endif
        <div class="sm:col-span-2"><p class="mb-2 text-sm font-medium">Ruoli</p><div class="grid gap-2 sm:grid-cols-2">
            @foreach($roles as $roleOption)
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-800"><input type="checkbox" name="roles[]" value="{{ $roleOption['value'] }}" @checked(in_array($roleOption['value'], old('roles', $user['roleIds'] ?? [])))>{{ $roleOption['label'] }}</label>
            @endforeach
        </div>@error('roles')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror</div>
    </div>
    <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-5 dark:border-gray-800"><x-ui.button :href="route('operational.users.index')" variant="secondary">Annulla</x-ui.button><x-ui.button type="submit">Salva</x-ui.button></div>
</form>
@if($editing && $abilities['resetPassword'])
    <form method="POST" action="{{ route('operational.users.password.update', $user['id']) }}" class="mt-6 max-w-3xl rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[.03]">@csrf @method('PUT')<h2 class="font-semibold text-gray-800 dark:text-white">Reimposta password</h2><div class="mt-4 grid gap-4 sm:grid-cols-2"><input name="password" type="password" required placeholder="Nuova password" class="h-11 rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700"><input name="password_confirmation" type="password" required placeholder="Conferma password" class="h-11 rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700"></div>@error('password')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror<div class="mt-4"><x-ui.button type="submit">Reimposta password</x-ui.button></div></form>
@endif
@if($editing && $user['isActive'] && $abilities['deactivate'])
    <form method="POST" action="{{ route('operational.users.deactivate', $user['id']) }}" class="mt-4">@csrf<button class="text-sm font-medium text-error-500">Disattiva utente</button></form>
@endif
@endsection
