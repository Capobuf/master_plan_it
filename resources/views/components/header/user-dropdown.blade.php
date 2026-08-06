<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button @click="open = !open" class="flex items-center gap-2 text-gray-700 dark:text-gray-300" type="button" aria-haspopup="menu" :aria-expanded="open">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/15">{{ str($user = auth()->user()?->name ?? 'U')->substr(0, 1)->upper() }}</span>
        <span class="hidden text-left sm:block"><span class="block text-sm font-medium">{{ $user }}</span><span class="block text-xs text-gray-500">{{ auth()->user()?->email }}</span></span>
        <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div x-show="open" x-transition class="absolute right-0 z-50 mt-3 w-60 rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900" style="display:none" role="menu">
        <div class="border-b border-gray-100 px-3 pb-3 dark:border-gray-800"><p class="text-sm font-medium text-gray-800 dark:text-white">{{ $user }}</p><p class="mt-0.5 text-xs text-gray-500">{{ auth()->user()?->email }}</p></div>
        <a href="{{ route('profile.edit') }}" class="mt-2 flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" role="menuitem">Profilo</a>
        <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-gray-100 pt-2 dark:border-gray-800">@csrf <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5" type="submit" role="menuitem">Esci</button></form>
    </div>
</div>
