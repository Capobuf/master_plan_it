<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Accesso | MasterPlan</title><script>if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark')}</script>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-full bg-white dark:bg-gray-900">
 <main class="grid min-h-screen lg:grid-cols-2">
  <section class="flex items-center justify-center px-6 py-12 sm:px-12"><div class="w-full max-w-md">
   <a href="{{ url('/') }}" class="mb-10 inline-flex items-center gap-3 font-bold text-brand-500"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white">M</span>MasterPlan</a>
   <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Accedi al tuo account</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Usa le credenziali aziendali per continuare.</p>
   <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">@csrf
    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="h-11 w-full rounded-lg border {{ $errors->has('email') ? 'border-error-500' : 'border-gray-300 dark:border-gray-700' }} bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:text-white" placeholder="nome@azienda.it">@error('email')<p class="mt-1.5 text-xs text-error-500">{{ $message }}</p>@enderror</div>
    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required class="h-11 w-full rounded-lg border {{ $errors->has('password') ? 'border-error-500' : 'border-gray-300 dark:border-gray-700' }} bg-transparent px-4 text-sm text-gray-800 outline-none focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:text-white" placeholder="Inserisci la password">@error('password')<p class="mt-1.5 text-xs text-error-500">{{ $message }}</p>@enderror</div>
    <button type="submit" class="flex h-11 w-full items-center justify-center rounded-lg bg-brand-500 text-sm font-medium text-white hover:bg-brand-600">Accedi</button>
   </form>
  </div></section>
  <aside class="relative hidden overflow-hidden bg-brand-500 p-12 lg:flex lg:flex-col lg:justify-between"><div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle at 1px 1px,#fff 1px,transparent 0);background-size:26px 26px"></div><div class="relative text-white"><p class="text-sm font-medium uppercase tracking-[0.2em]">MasterPlan</p><h2 class="mt-6 max-w-md text-4xl font-semibold leading-tight">Gestisci budget, spese e contratti da un unico luogo.</h2></div><p class="relative text-sm text-white/80">Pianificazione economica per il tuo team.</p></aside>
 </main>
</body></html>
