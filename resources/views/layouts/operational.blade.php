@php
    /** @var \App\Domain\Tenancy\Data\TenantContext $tenantContext */
    $tenantContext = request()->attributes->get(\App\Domain\Tenancy\Data\TenantContext::class);
    $actor = auth()->user();
    $actorName = trim((string) ($actor?->name ?: 'Utente'));
    $actorInitials = collect(preg_split('/\s+/', $actorName) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Workspace operativo · Master Plan IT</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body x-data="{ sidebarCollapsed: false, desktop: window.innerWidth >= 1024, init() { window.addEventListener('resize', () => { this.desktop = window.innerWidth >= 1024 }) } }" class="min-h-screen overflow-x-clip bg-slate-50 text-slate-950 antialiased">
        <aside
            id="operational-sidebar"
            :class="desktop && sidebarCollapsed ? 'lg:w-20' : 'lg:w-64'"
            class="hs-overlay fixed inset-y-0 start-0 z-[60] hidden w-64 -translate-x-full transform flex-col overflow-y-auto border-e border-white/10 bg-[#071a38] text-slate-100 transition-all duration-300 hs-overlay-open:translate-x-0 lg:flex lg:translate-x-0"
            aria-label="Navigazione principale"
        >
            <div class="flex h-[68px] items-center gap-3 border-b border-white/10 px-5">
                <svg aria-hidden="true" viewBox="0 0 42 32" class="h-8 w-10 shrink-0 text-blue-400" fill="none">
                    <path d="M3 27 14 5l8 15L29 4l10 23" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="m11 27 7-12 7 12" stroke="#93c5fd" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div x-show="!desktop || !sidebarCollapsed" x-cloak>
                    <p class="text-lg font-semibold tracking-tight text-white">Master Plan IT</p>
                    <p class="text-[10px] font-medium uppercase tracking-[0.16em] text-slate-400">Plan · Control · Value</p>
                </div>
                <button type="button" data-hs-overlay="#operational-sidebar" aria-controls="operational-sidebar" aria-label="Chiudi navigazione" class="ms-auto inline-flex size-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white lg:hidden">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <nav class="flex-1 space-y-6 px-3 py-5 text-sm" aria-label="Moduli applicativi">
                @can('dashboard.view')
                    <div>
                        <a href="{{ route('operational.index') }}" @if (request()->routeIs('operational.index')) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium text-slate-200 transition hover:bg-white/8 hover:text-white aria-[current=page]:bg-blue-600 aria-[current=page]:text-white aria-[current=page]:shadow-lg aria-[current=page]:shadow-blue-950/30">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/></svg>
                            <span x-show="!desktop || !sidebarCollapsed" x-cloak>Panoramica</span>
                        </a>
                    </div>
                @endcan

                <div>
                    <p x-show="!desktop || !sidebarCollapsed" x-cloak class="px-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Analisi e controllo</p>
                    <div class="mt-2 space-y-1">
                        @can('viewAny', \App\Models\CostCenter::class)
                            <span class="flex cursor-not-allowed items-center justify-between gap-3 rounded-lg px-3 py-2 text-slate-500" aria-disabled="true">
                                <span class="flex items-center gap-3">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 4v16M4 12h16"/></svg>
                                    <span x-show="!desktop || !sidebarCollapsed" x-cloak>Centri di costo</span>
                                </span>
                                <span x-show="!desktop || !sidebarCollapsed" x-cloak class="rounded bg-white/8 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide">In arrivo</span>
                            </span>
                        @endcan
                        @can('viewAny', \App\Models\Vendor::class)
                            <span class="flex cursor-not-allowed items-center justify-between gap-3 rounded-lg px-3 py-2 text-slate-500" aria-disabled="true">
                                <span class="flex items-center gap-3">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V7l8-4 8 4v14"/><path d="M8 10h2m4 0h2M8 14h2m4 0h2M9 21v-3h6v3"/></svg>
                                    <span x-show="!desktop || !sidebarCollapsed" x-cloak>Fornitori</span>
                                </span>
                                <span x-show="!desktop || !sidebarCollapsed" x-cloak class="rounded bg-white/8 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide">In arrivo</span>
                            </span>
                        @endcan
                        @can('viewAny', \App\Models\Expense::class)
                            <a href="{{ route('operational.expenses.index') }}" @if (request()->routeIs('operational.expenses.*')) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium text-slate-200 transition hover:bg-white/8 hover:text-white aria-[current=page]:bg-blue-600 aria-[current=page]:text-white aria-[current=page]:shadow-lg aria-[current=page]:shadow-blue-950/30">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2Z"/><path d="M8 8h8m-8 4h8"/></svg>
                                <span x-show="!desktop || !sidebarCollapsed" x-cloak>Spese</span>
                            </a>
                        @endcan
                    </div>
                </div>

                <div>
                    <p x-show="!desktop || !sidebarCollapsed" x-cloak class="px-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Pianificazione</p>
                    <div class="mt-2 space-y-1">
                        @can('viewAny', \App\Models\PlanningYear::class)
                            <span class="flex cursor-not-allowed items-center justify-between gap-3 rounded-lg px-3 py-2 text-slate-500" aria-disabled="true">
                                <span class="flex items-center gap-3">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18"/></svg>
                                    <span x-show="!desktop || !sidebarCollapsed" x-cloak>Anni di pianificazione</span>
                                </span>
                                <span x-show="!desktop || !sidebarCollapsed" x-cloak class="rounded bg-white/8 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide">In arrivo</span>
                            </span>
                        @endcan
                        <span class="flex cursor-not-allowed items-center justify-between gap-3 rounded-lg px-3 py-2 text-slate-500" aria-disabled="true">
                            <span class="flex items-center gap-3">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg>
                                <span x-show="!desktop || !sidebarCollapsed" x-cloak>Budget corrente</span>
                            </span>
                            <span x-show="!desktop || !sidebarCollapsed" x-cloak class="rounded bg-white/8 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide">Prossimo</span>
                        </span>
                    </div>
                </div>

                @if ($actor !== null && $actor->tenant_id === null)
                    <div>
                        <p x-show="!desktop || !sidebarCollapsed" x-cloak class="px-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Amministrazione piattaforma</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ url('/admin') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-slate-300 transition hover:bg-white/8 hover:text-white">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7Z"/><path d="M9 12h6m-3-3v6"/></svg>
                                <span x-show="!desktop || !sidebarCollapsed" x-cloak>Console amministrativa</span>
                            </a>
                        </div>
                    </div>
                @endif
            </nav>

            <div x-show="!desktop || !sidebarCollapsed" x-cloak class="border-t border-white/10 px-5 py-4 text-xs text-slate-400">
                Milestone · Expense → Budget
            </div>
        </aside>

        <div dusk="operational-shell" :class="desktop && sidebarCollapsed ? 'lg:ps-20' : 'lg:ps-64'" class="min-h-screen min-w-0 max-w-full overflow-x-clip transition-[padding] duration-300">
            <header class="sticky top-0 z-40 flex h-[68px] items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6 lg:px-8">
                <button type="button" data-hs-overlay="#operational-sidebar" aria-controls="operational-sidebar" aria-label="Apri navigazione" class="inline-flex size-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 lg:hidden">
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>

                <button type="button" @click="sidebarCollapsed = !sidebarCollapsed" aria-controls="operational-sidebar" :aria-expanded="(!sidebarCollapsed).toString()" :aria-label="sidebarCollapsed ? 'Espandi navigazione' : 'Riduci navigazione'" class="hidden size-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 lg:inline-flex">
                    <svg x-show="!sidebarCollapsed" aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg>
                    <svg x-show="sidebarCollapsed" x-cloak aria-hidden="true" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg>
                </button>

                <div class="ml-auto flex min-w-0 items-center gap-2 sm:gap-4">
                    <div class="min-w-0 max-w-[11rem] rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 shadow-sm sm:min-w-56 sm:max-w-xs sm:px-3 sm:py-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Tenant corrente</p>
                        <p aria-live="polite" aria-atomic="true" class="mt-0.5 truncate text-xs font-semibold text-slate-800 sm:text-sm">
                            <span class="sr-only">Contesto tenant: </span>{{ $tenantContext?->tenant->name ?? 'Tenant non selezionato' }}@if ($tenantContext?->tenant) <span class="hidden sm:inline">({{ $tenantContext->tenant->code }})</span> @endif
                        </p>
                    </div>
                    <div class="hidden h-9 w-px bg-slate-200 sm:block"></div>
                    <div class="hs-dropdown [--placement:bottom-right] relative inline-flex">
                        <button id="operational-user-menu" type="button" class="hs-dropdown-toggle inline-flex items-center gap-3 rounded-lg p-1 text-left hover:bg-slate-50 focus:outline-none" aria-haspopup="menu" aria-label="Apri menu utente">
                            <span class="inline-flex size-9 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">{{ $actorInitials ?: 'UI' }}</span>
                            <span class="hidden sm:block">
                                <span class="block max-w-44 truncate text-sm font-semibold text-slate-800">{{ $actorName }}</span>
                                <span class="block text-xs text-slate-500">{{ $actor?->tenant_id === null ? 'Administrator' : 'Tenant user' }}</span>
                            </span>
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-slate-400 sm:block" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 10 5 5 5-5"/></svg>
                        </button>
                        <div class="hs-dropdown-menu duration mt-2 hidden min-w-56 rounded-xl border border-slate-200 bg-white p-1 shadow-lg" role="menu" aria-orientation="vertical" aria-labelledby="operational-user-menu">
                            <div class="border-b border-slate-100 px-3 py-2 sm:hidden"><p class="truncate text-sm font-semibold text-slate-900">{{ $actorName }}</p></div>
                            <a href="{{ route('filament.admin.pages.profile') }}" role="menuitem" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Profilo</a>
                            @if ($actor !== null && $actor->tenant_id === null)
                                <a href="{{ url('/admin') }}" role="menuitem" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Console amministrativa</a>
                            @endif
                            <form method="POST" action="{{ route('filament.admin.auth.logout') }}" class="border-t border-slate-100 pt-1">
                                @csrf
                                <button type="submit" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-red-700 hover:bg-red-50">Esci</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-7">
                @isset($slot)
                    {{ $slot }}
                @else
                    <x-operational.breadcrumb :items="[
                        ['label' => 'Panoramica'],
                        ['label' => 'Workspace operativo'],
                    ]" />

                    <x-operational.page-header class="mt-5" title="Panoramica operativa" description="Visibilità e accesso ai dati correnti del tenant selezionato.">
                        <x-slot:actions><x-operational.status-badge tone="success" class="px-3 py-1.5 text-xs normal-case tracking-normal"><span class="size-1.5 rounded-full bg-emerald-500"></span>Contesto attivo</x-operational.status-badge></x-slot:actions>
                    </x-operational.page-header>

                    @can('viewAny', \App\Models\Expense::class)
                        <section class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]" aria-labelledby="expense-module-title">
                            <x-operational.card class="overflow-hidden">
                                <div class="flex items-start justify-between gap-6 border-b border-slate-100 p-6">
                                    <div>
                                        <x-operational.status-badge tone="info" class="rounded-md">Modulo disponibile</x-operational.status-badge>
                                        <h2 id="expense-module-title" class="mt-4 text-xl font-bold text-slate-950">Registro spese correnti</h2>
                                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Consulta le spese del tenant, filtra per anno e apri il dettaglio con totali calcolati dal server.</p>
                                    </div>
                                    <span class="hidden rounded-lg bg-blue-600/10 p-3 text-blue-700 sm:inline-flex">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2Z"/><path d="M8 8h8m-8 4h8"/></svg>
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-4 bg-slate-50/70 px-6 py-4">
                                    <p class="text-xs text-slate-500">Dataset corrente · nessuna revisione storica inclusa</p>
                                    <a href="{{ route('operational.expenses.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                                        Apri registro
                                        <span aria-hidden="true">→</span>
                                    </a>
                                </div>
                            </x-operational.card>

                            <x-operational.card class="p-5" aria-label="Disponibilità workspace">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Disponibilità</p>
                                <p class="mt-2 font-semibold text-slate-900">Dati Expense correnti</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Il registro mostra solo il dataset corrente autorizzato per questo tenant. Le revisioni restano escluse.</p>
                                <div class="mt-5 border-t border-slate-100 pt-4"><x-operational.status-badge tone="success">Disponibile</x-operational.status-badge></div>
                            </x-operational.card>
                        </section>
                    @else
                        <div class="mt-6">
                            <x-operational.empty-state
                                title="Nessun modulo operativo disponibile"
                                description="Il tuo account non dispone ancora di moduli operativi per questo tenant."
                            />
                        </div>
                    @endcan

                    <button
                        type="button"
                        dusk="workspace-help-open"
                        aria-haspopup="dialog"
                        aria-expanded="false"
                        aria-controls="operational-workspace-help"
                        data-hs-overlay="#operational-workspace-help"
                        class="mt-8 inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        Workspace help
                    </button>

                    <div id="operational-workspace-help" dusk="workspace-help" class="hs-overlay pointer-events-none fixed start-0 top-0 z-70 hidden size-full overflow-x-hidden overflow-y-auto bg-slate-950/40" role="dialog" tabindex="-1" aria-labelledby="operational-workspace-help-title">
                        <div class="m-3 opacity-0 transition-all hs-overlay-open:duration-300 hs-overlay-open:opacity-100 sm:mx-auto sm:mt-20 sm:w-full sm:max-w-lg">
                            <div class="pointer-events-auto rounded-xl bg-white p-6 shadow-2xl">
                                <div class="flex items-start justify-between gap-4">
                                    <h2 id="operational-workspace-help-title" class="text-lg font-semibold">Informazioni sul workspace</h2>
                                    <button type="button" dusk="workspace-help-close" autofocus aria-label="Chiudi informazioni workspace" data-hs-overlay="#operational-workspace-help" class="rounded-lg px-2 py-1 text-sm font-medium text-blue-700 hover:bg-blue-50">Chiudi</button>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-slate-600">Il workspace usa sempre il tenant mostrato nella barra superiore. I collegamenti sono filtrati per permission e ogni richiesta viene autorizzata nuovamente dal server.</p>
                                @if ($actor !== null && $actor->tenant_id === null)
                                    <p class="mt-3 text-sm leading-6 text-slate-600">La console amministrativa è separata dal workspace operativo ed è riservata all'amministratore di piattaforma.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endisset
            </main>
        </div>
        @livewireScripts
    </body>
</html>
