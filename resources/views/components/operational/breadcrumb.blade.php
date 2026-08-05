@props(['items' => []])

<nav aria-label="Breadcrumb" {{ $attributes->class('flex flex-wrap items-center gap-y-1 text-xs font-medium text-slate-500') }}>
    @foreach ($items as $item)
        @if (! $loop->first)
            <span class="mx-2 text-slate-300" aria-hidden="true">›</span>
        @endif

        @if (isset($item['href']))
            <a href="{{ $item['href'] }}" class="rounded px-1 py-0.5 hover:text-blue-700 hover:underline">{{ $item['label'] }}</a>
        @elseif ($loop->last)
            <span class="rounded bg-slate-100 px-2 py-1 text-slate-700">{{ $item['label'] }}</span>
        @else
            <span>{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
