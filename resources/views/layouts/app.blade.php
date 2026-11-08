<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? nr_setting('site_description', __('Next Respack is a self-hosted Minecraft Java Edition resource pack hosting platform.')) }}">
    <title>@yield('title', config('app.name')) — {{ config('app.name') }}</title>
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
<body class="flex min-h-full flex-col" x-data="themeToggle">
    <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/80">
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm dark:bg-emerald-500 dark:text-slate-950">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M12 3 3.5 7.5v9L12 21l8.5-4.5v-9L12 3Z" stroke-linejoin="round"/>
                        <path d="M3.5 7.5 12 12l8.5-4.5M12 12v9" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="text-base font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ config('app.name') }}</span>
            </a>

            <nav class="hidden items-center gap-1 md:flex">
                <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('home') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    {{ __('Home') }}
                </a>
                <a href="{{ route('pack-formats.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('pack-formats.*') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    {{ __('Pack formats') }}
                </a>
                @guest
                    <a href="{{ route('guest.upload') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('guest.upload*') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        {{ __('Guest upload') }}
                    </a>
                @endguest
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        {{ __('Dashboard') }}
                    </a>
                    <a href="{{ route('packs.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('packs.*') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        {{ __('My resource packs') }}
                    </a>
                @endauth
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('locale', app()->getLocale() === 'zh_CN' ? 'en' : 'zh_CN') }}"
                   class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                   title="{{ __('Switch language') }}">
                    {{ app()->getLocale() === 'zh_CN' ? 'EN' : '中文' }}
                </a>
                <button type="button" @click="toggle()" class="rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" title="{{ __('Toggle dark mode') }}">
                    <svg x-show="!dark" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.8A8.5 8.5 0 0 1 11.2 3a7 7 0 1 0 9.8 9.8Z"/></svg>
                    <svg x-show="dark" x-cloak viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.6 4.6l1.8 1.8M17.6 17.6l1.8 1.8M19.4 4.6l-1.8 1.8M6.4 17.6l-1.8 1.8"/></svg>
                </button>

                @auth
                    <a href="{{ route('admin.dashboard') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:inline-flex dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Admin') }}
                    </a>
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-3 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-xs font-bold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                {{ mb_strtoupper(mb_substr(auth()->user()->displayName(), 0, 1)) }}
                            </span>
                            <span class="max-w-24 truncate">{{ auth()->user()->displayName() }}</span>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 mt-2 w-48 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                            <a href="{{ route('account.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">{{ __('Account settings') }}</a>
                            <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">{{ __('Dashboard') }}</a>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">{{ __('Admin panel') }}</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">{{ __('Sign out') }}</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="nr-btn-ghost">{{ __('Sign in') }}</a>
                    @if (nr_setting_bool('allow_registration', true))
                        <a href="{{ route('register') }}" class="nr-btn-primary">{{ __('Create account') }}</a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-1">
        <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            @include('components.flash')
            @yield('content')
        </div>
    </main>

    <footer class="mt-auto border-t border-slate-200 bg-white py-6 dark:border-slate-800 dark:bg-slate-950">
        <div class="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-3 px-4 text-sm text-slate-500 sm:flex-row sm:px-6 lg:px-8 dark:text-slate-400">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }} · {{ __('Minecraft resource pack hosting') }}</p>
            <div class="flex items-center gap-4">
                <a href="{{ route('pack-formats.index') }}" class="hover:text-emerald-600 dark:hover:text-emerald-400">{{ __('Pack format database') }}</a>
                <a href="{{ route('pack-formats.api') }}" class="hover:text-emerald-600 dark:hover:text-emerald-400">API</a>
                <span class="font-mono text-xs">v{{ config('nextrespack.version') }}</span>
            </div>
        </div>
        <div class="mx-auto mt-4 w-full max-w-7xl border-t border-slate-100 px-4 pt-4 text-center text-xs text-slate-500 sm:px-6 lg:px-8 dark:border-slate-800 dark:text-slate-400">
            <p>
                {{ __('This is an open source project') }} · MIT ·
                <a href="https://github.com/catmomouwu/next-respack" target="_blank" rel="noopener noreferrer" class="font-mono font-medium text-slate-600 underline-offset-4 hover:text-emerald-600 hover:underline dark:text-slate-300 dark:hover:text-emerald-400">github.com/catmomouwu/next-respack ↗</a>
            </p>
        </div>
    </footer>
</body>
</html>
