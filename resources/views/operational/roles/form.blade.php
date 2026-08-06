@php($editing = isset($role))
@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="$pageTitle" />
<form method="POST" action="{{ $editing ? route('operational.roles.update', $role['id']) : route('operational.roles.store') }}" class="max-w-3xl rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[.03]">
    @csrf
    @if($editing)
        @method('PUT')
    @endif
    <div class="p-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="name">Nome ruolo</label>
        <input id="name" name="name" required value="{{ old('name', $role['name'] ?? '') }}" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700">
        @error('name')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
        <h2 class="mt-6 text-sm font-medium text-gray-700 dark:text-gray-300">Abilità</h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach($abilities as $ability)
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-300"><input name="abilities[]" type="checkbox" value="{{ $ability['value'] }}" @checked(in_array($ability['value'], old('abilities', $role['abilities'] ?? [])))>{{ $ability['label'] }}</label>
            @endforeach
        </div>
        @error('abilities')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
    </div>
    <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-5 dark:border-gray-800"><x-ui.button :href="route('operational.roles.index')" variant="secondary">Annulla</x-ui.button><x-ui.button type="submit">Salva ruolo</x-ui.button></div>
</form>
@endsection
