<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Revision history — {{ $record->name }}</x-slot>

        <ul>
            @forelse ($batches as $batch)
                <li>
                    {{ $batch->operation->value }} · {{ $batch->occurred_at?->toDateTimeString() }}
                    @if ($batch->items->isNotEmpty() && \App\Filament\Resources\Vendors\VendorResource::canRestoreRevision($record))
                        <button type="button" wire:click="restore({{ $batch->items->first()->version_id }})">
                            Restore
                        </button>
                    @endif
                </li>
            @empty
                <li>No revisions recorded.</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-panels::page>
