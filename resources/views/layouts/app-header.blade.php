<header class="sticky top-0 z-9999 flex w-full border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
 <div class="flex w-full items-center justify-between px-3 py-3 lg:px-6 lg:py-4">
  <button class="hidden h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 xl:flex dark:border-gray-800" @click="$store.sidebar.toggleExpanded()" aria-label="Riduci menu laterale">
   <svg width="16" height="12" viewBox="0 0 16 12" fill="none"><path d="M1 1h14M1 6h7M1 11h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
  </button>
  <button class="flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 xl:hidden" @click="$store.sidebar.toggleMobileOpen()" aria-label="Apri menu laterale"><svg width="16" height="12" viewBox="0 0 16 12" fill="none"><path d="M1 1h14M1 6h7M1 11h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></button>
  <div class="ml-auto flex items-center gap-3"><x-common.theme-toggle /><x-header.user-dropdown /></div>
 </div>
</header>
