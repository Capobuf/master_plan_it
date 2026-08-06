@php($groups = \App\Helpers\MenuHelper::groups())
<aside x-data
    class="fixed inset-y-0 left-0 z-99999 flex h-screen w-[290px] -translate-x-full flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out xl:translate-x-0 dark:border-gray-800 dark:bg-gray-900"
    :class="[$store.sidebar.isMobileOpen ? 'translate-x-0' : '-translate-x-full', ($store.sidebar.isExpanded || $store.sidebar.isHovered) ? 'xl:w-[290px]' : 'xl:w-[90px]']"
    @mouseenter="$store.sidebar.setHovered(true)" @mouseleave="$store.sidebar.setHovered(false)">
    <div class="flex pt-8 pb-7" :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'xl:justify-center' : 'justify-start'">
        <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold text-brand-500">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500 text-white">M</span>
            <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen" x-cloak>MasterPlan</span>
        </a>
    </div>
    <nav class="no-scrollbar flex-1 overflow-y-auto">
        <div class="flex flex-col gap-6">
            @foreach ($groups as $group)
                @php($items = array_values(array_filter($group['items'], fn ($item) => $item['visible'])))
                @if ($items !== [])
                    <section>
                        <h2 class="mb-4 flex text-xs uppercase leading-5 text-gray-400" :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'xl:justify-center' : 'justify-start'">
                            <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">{{ $group['title'] }}</span>
                            <span x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen" class="hidden xl:block">•••</span>
                        </h2>
                        <ul class="flex flex-col gap-1">
                            @foreach ($items as $item)
                                <li><a href="{{ $item['path'] }}" @click="$store.sidebar.closeMobile()" class="menu-item group" :class="[window.location.pathname === new URL('{{ $item['path'] }}').pathname ? 'menu-item-active' : 'menu-item-inactive', (!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'xl:justify-center' : 'justify-start']">
                                    <span class="menu-item-icon-inactive group-[.menu-item-active]:menu-item-icon-active">@include('components.ui.icon', ['name' => $item['icon']])</span>
                                    <span class="menu-item-text" x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">{{ $item['name'] }}</span>
                                </a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        </div>
    </nav>
</aside>
<div x-show="$store.sidebar.isMobileOpen" x-transition.opacity @click="$store.sidebar.closeMobile()" class="fixed inset-0 z-9999 bg-gray-900/50 xl:hidden" style="display:none"></div>
