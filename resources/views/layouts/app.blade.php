<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'MasterPlan' }}</title>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('sidebar', {
                isExpanded: localStorage.getItem('sidebar-expanded') !== 'false', isMobileOpen: false, isHovered: false,
                toggleExpanded() { this.isExpanded = !this.isExpanded; localStorage.setItem('sidebar-expanded', String(this.isExpanded)); this.isMobileOpen = false; },
                toggleMobileOpen() { this.isMobileOpen = !this.isMobileOpen; },
                setHovered(value) { if (window.innerWidth >= 1280 && !this.isExpanded) this.isHovered = value; },
                closeMobile() { this.isMobileOpen = false; },
            });
        });
    </script>
</head>
<body class="min-h-full bg-gray-50 dark:bg-gray-900" x-data @keydown.escape.window="$store.sidebar.closeMobile()">
    <div class="min-h-screen xl:flex">
        @include('layouts.sidebar')
        <div class="flex-1 transition-all duration-300 ease-in-out" :class="$store.sidebar.isExpanded ? 'xl:ml-[290px]' : 'xl:ml-[90px]'">
            @include('layouts.app-header')
            <main class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">@yield('content')</main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
