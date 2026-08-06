@php($editing = isset($costCenter))
@extends('layouts.app')

@section('content')
<x-common.page-breadcrumb :pageTitle="$pageTitle" />
<form method="POST" action="{{ $editing ? route('operational.cost-centers.update', $costCenter['id']) : route('operational.cost-centers.store') }}" class="max-w-2xl rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[.03]">
    @csrf
    @if($editing)
        @method('PUT')
        <input name="lock_version" type="hidden" value="{{ $costCenter['lockVersion'] }}">
    @endif
    <div class="p-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="name">Nome</label>
        <input id="name" name="name" value="{{ old('name', $costCenter['name'] ?? '') }}" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700">
        @error('name')<p class="mt-1 text-xs text-error-500">{{ $message }}</p>@enderror
        <label class="mb-1.5 mt-5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="parent_id">Centro padre</label>
        <select id="parent_id" name="parent_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700"><option value="">Nessuno</option>@foreach($parents as $parent)<option value="{{ $parent['value'] }}" @selected((string) old('parent_id', $costCenter['parentId'] ?? '') === (string) $parent['value'])>{{ $parent['label'] }}{{ !$parent['active'] ? ' · inattivo' : '' }}</option>@endforeach</select>
    </div>
    <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-5 dark:border-gray-800"><x-ui.button :href="route('operational.cost-centers.index')" variant="secondary">Annulla</x-ui.button><x-ui.button type="submit">Salva</x-ui.button></div>
</form>
@if($editing)
    <div class="mt-6 flex gap-3">
        @if($costCenter['active'] && $abilities['deactivate'])
            <form method="POST" action="{{ route('operational.cost-centers.deactivate', $costCenter['id']) }}">@csrf<input name="lock_version" type="hidden" value="{{ $costCenter['lockVersion'] }}"><button class="text-sm font-medium text-warning-600">Disattiva</button></form>
        @elseif(!$costCenter['active'] && $abilities['reactivate'])
            <form method="POST" action="{{ route('operational.cost-centers.reactivate', $costCenter['id']) }}">@csrf<input name="lock_version" type="hidden" value="{{ $costCenter['lockVersion'] }}"><button class="text-sm font-medium text-success-600">Riattiva</button></form>
        @endif
        @if($abilities['delete'])
            <form method="POST" action="{{ route('operational.cost-centers.destroy', $costCenter['id']) }}">@csrf @method('DELETE')<input name="lock_version" type="hidden" value="{{ $costCenter['lockVersion'] }}"><button class="text-sm font-medium text-error-500">Elimina</button></form>
        @endif
    </div>
@endif
@endsection
