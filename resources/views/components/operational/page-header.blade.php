@props(['title', 'description' => null])

<header {{ $attributes->class('flex flex-col gap-4 border-b border-slate-200 pb-6 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $title }}</h1>
            {{ $badge ?? '' }}
        </div>
        @if ($description)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
