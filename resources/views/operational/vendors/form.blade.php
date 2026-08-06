@php($isEdit = isset($vendor))
@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$isEdit ? 'Modifica fornitore' : 'Nuovo fornitore'" />
    <form method="POST" action="{{ $isEdit ? route('operational.vendors.update', $vendor['id']) : route('operational.vendors.store') }}" class="max-w-3xl rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @csrf
        @if($isEdit) @method('PUT') <input type="hidden" name="lock_version" value="{{ $vendor['lockVersion'] }}"> @endif
        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800"><h1 class="text-base font-medium text-gray-800 dark:text-white/90">Dati del fornitore</h1><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">I campi contrassegnati sono obbligatori.</p></div>
        <div class="grid gap-5 p-6 sm:grid-cols-2">
            @foreach([['name','Nome', 'text', true], ['vat_number','Partita IVA', 'text', false], ['email','Email', 'email', false], ['phone','Telefono', 'text', false]] as [$field, $label, $type, $required])
                <div><label for="{{ $field }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">{{ $label }} @if($required)<span class="text-error-500">*</span>@endif</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $isEdit ? ($vendor[match($field) {'vat_number' => 'vatNumber', default => $field}] ?? '') : '') }}" @required($required) @class(['h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90', 'border-error-300 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500/50' => $errors->has($field), 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700' => !$errors->has($field)])>@error($field)<p class="mt-1.5 text-xs text-error-500">{{ $message }}</p>@enderror</div>
            @endforeach
            <div class="sm:col-span-2"><label for="address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Indirizzo</label><textarea id="address" name="address" rows="4" class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">{{ old('address', $isEdit ? $vendor['address'] : '') }}</textarea>@error('address')<p class="mt-1.5 text-xs text-error-500">{{ $message }}</p>@enderror</div>
        </div>
        <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-5 dark:border-gray-800"><a href="{{ route('operational.vendors.index') }}" class="inline-flex items-center justify-center rounded-lg px-5 py-3.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/[0.05]">Annulla</a><x-ui.button type="submit">{{ $isEdit ? 'Salva modifiche' : 'Crea fornitore' }}</x-ui.button></div>
    </form>
@endsection
