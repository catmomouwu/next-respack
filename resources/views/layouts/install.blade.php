<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Install Next Respack')) — Next Respack</title>
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
<body class="min-h-full bg-slate-100 dark:bg-slate-950" x-data="themeToggle">
    <div class="nr-glow min-h-screen">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-6 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20 dark:bg-emerald-500 dark:text-slate-950">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 3.5 7.5v9L12 21l8.5-4.5v-9L12 3Z" stroke-linejoin="round"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9" stroke-linejoin="round"/></svg>
                </span>
                <div>
                    <p class="text-lg font-extrabold tracking-tight text-slate-900 dark:text-slate-100">Next Respack</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Installation wizard') }} · v{{ config('nextrespack.version') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('locale', app()->getLocale() === 'zh_CN' ? 'en' : 'zh_CN') }}" class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                    {{ app()->getLocale() === 'zh_CN' ? 'English' : '简体中文' }}
                </a>
                <button type="button" @click="toggle()" class="rounded-lg border border-slate-300 bg-white p-2 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                    <svg x-show="!dark" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.8A8.5 8.5 0 0 1 11.2 3a7 7 0 1 0 9.8 9.8Z"/></svg>
                    <svg x-show="dark" x-cloak viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22"/></svg>
                </button>
            </div>
        </header>

        @hasSection('steps')
            <div class="mx-auto w-full max-w-5xl px-4 pb-6 sm:px-6">
                @yield('steps')
            </div>
        @endif

        <main class="mx-auto w-full max-w-5xl px-4 pb-16 sm:px-6">
            <div class="nr-card overflow-hidden">
                <div class="border-b border-slate-200 bg-white px-6 py-5 dark:border-slate-800 dark:bg-slate-900">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">@yield('heading')</h1>
                    @hasSection('subheading')
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">@yield('subheading')</p>
                    @endif
                </div>
                <div class="p-6 sm:p-8">
                    @include('components.flash')
                    @yield('content')
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-slate-400 dark:text-slate-600">
                {{ __('Next Respack is self-hosted. Your files and data stay on your server.') }}
            </p>
        </main>
    </div>
</body>
</html>
