@props(['title', 'description', 'actionLabel' => null, 'actionHref' => null])

<section data-state="empty" aria-labelledby="operational-empty-title" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center shadow-sm">
    <span class="mx-auto inline-flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2Z"/><path d="M8 9h8m-8 4h5"/></svg>
    </span>
    <h2 id="operational-empty-title" class="mt-4 text-base font-bold text-slate-900">{{ $title }}</h2>
    <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">{{ $description }}</p>
    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">{{ $actionLabel }}</a>
    @endif
</section>
