<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Revision history — {{ $record->name }}</x-slot>

        <ul>
            @forelse ($batches as $batch)
                <li>{{ $batch->operation->value }} · {{ $batch->occurred_at?->toDateTimeString() }}</li>
            @empty
                <li>No revisions recorded.</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-panels::page>
