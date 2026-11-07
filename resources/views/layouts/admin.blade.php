<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Admin panel')) — {{ config('app.name') }}</title>
    <script>
        (function () {
            const stored = localStorage.getItem('nr-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full" x-data="themeToggle">
    <div class="flex min-h-screen flex-col lg:flex-row">
        <aside class="shrink-0 border-b border-slate-200 bg-white lg:w-64 lg:border-b-0 lg:border-r dark:border-slate-800 dark:bg-slate-950">
            <div class="flex items-center gap-2.5 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white dark:bg-emerald-500 dark:text-slate-950">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 3.5 7.5v9L12 21l8.5-4.5v-9L12 3Z" stroke-linejoin="round"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9" stroke-linejoin="round"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ config('app.name') }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Admin panel') }}</p>
                    </div>
                </a>
            </div>

            <nav class="flex flex-wrap gap-1 p-3 lg:flex-col">
                @php
                    $links = [
                        ['route' => 'admin.dashboard', 'label' => __('Overview'), 'pattern' => 'admin.dashboard'],
                        ['route' => 'admin.users.index', 'label' => __('Users'), 'pattern' => 'admin.users.*'],
                        ['route' => 'admin.packs.index', 'label' => __('Resource packs'), 'pattern' => 'admin.packs.*'],
                        ['route' => 'admin.pack-formats.index', 'label' => __('Pack format data'), 'pattern' => 'admin.pack-formats.*'],
                        ['route' => 'admin.settings.edit', 'label' => __('Settings'), 'pattern' => 'admin.settings.*'],
                        ['route' => 'admin.logs.index', 'label' => __('Audit & download logs'), 'pattern' => 'admin.logs.*'],
                    ];
                @endphp
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}" class="rounded-xl px-3.5 py-2.5 text-sm font-medium transition {{ request()->routeIs($link['pattern']) ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
                @if (auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.storage.index') }}" class="rounded-xl px-3.5 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.storage.*') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        {{ __('Storage') }}
                    </a>
                @endif
            </nav>

            <div class="mt-auto hidden border-t border-slate-200 px-4 py-4 lg:block dark:border-slate-800">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->displayName() }}</span>
                    <div class="flex gap-1">
                        <a href="{{ route('locale', app()->getLocale() === 'zh_CN' ? 'en' : 'zh_CN') }}" class="rounded-md border border-slate-200 px-1.5 py-0.5 text-[10px] font-semibold dark:border-slate-700 dark:text-slate-300">{{ app()->getLocale() === 'zh_CN' ? 'EN' : '中文' }}</a>
                        <button type="button" @click="toggle()" class="rounded-md border border-slate-200 px-1.5 py-0.5 text-[10px] dark:border-slate-700 dark:text-slate-300">◐</button>
                    </div>
                </div>
                <a href="{{ route('home') }}" class="block text-xs text-slate-500 hover:text-emerald-600 dark:text-slate-400">{{ __('Back to site') }}</a>
            </div>
        </aside>

        <div class="min-w-0 flex-1 bg-slate-50 dark:bg-slate-950">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900">
                <h1 class="text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100">@yield('heading', __('Admin panel'))</h1>
                <div class="flex items-center gap-2">
                    <a href="{{ route('dashboard') }}" class="nr-btn-secondary !py-2 !px-3 text-xs">{{ __('User dashboard') }}</a>
                </div>
            </header>

            <div class="p-4 sm:p-6">
                @include('components.flash')
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
