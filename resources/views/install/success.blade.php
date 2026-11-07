@extends('layouts.install')

@section('title', __('Installation complete'))
@section('heading', __('Installation complete'))
@section('subheading', __('Next Respack is installed and ready to use. The installer is now locked.'))
@section('steps')
    @include('components.install-steps', ['current' => 7])
@endsection

@section('content')
    <div class="text-center">
        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
            <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 4.5 4.5L19 7"/></svg>
        </span>
        <h2 class="mt-4 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ __('Welcome to :name', ['name' => config('app.name')]) }}</h2>
        <p class="mx-auto mt-2 max-w-xl text-sm text-slate-600 dark:text-slate-300">
            {{ __('Version :version was installed successfully. The installation lock and database record prevent the installer from being opened again.', ['version' => $result['version'] ?? config('nextrespack.version')]) }}
        </p>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Administrator') }}</p>
            <p class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $result['admin_username'] ?? '' }}</p>
            <p class="mt-1 break-all font-mono text-xs text-slate-500 dark:text-slate-400">{{ $result['admin_email'] ?? '' }}</p>
        </div>
        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Site URL') }}</p>
            <p class="mt-2 break-all font-mono text-xs text-slate-700 dark:text-slate-300">{{ $result['url'] ?? '' }}</p>
        </div>
        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Next steps') }}</p>
            <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ __('Sign in, review settings and configure SMTP before enabling email verification.') }}</p>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('login') }}" class="nr-btn-primary !px-6">{{ __('Sign in to the admin panel') }}</a>
        <a href="{{ route('home') }}" class="nr-btn-secondary">{{ __('View the site') }}</a>
    </div>

    <div class="nr-warning mt-8 text-left">
        <p class="font-semibold">{{ __('Post-installation checklist') }}</p>
        <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
            <li>{{ __('Delete any leftover installation files only if your deployment guide instructs you to. The installer routes are disabled automatically.') }}</li>
            <li>{{ __('Set up HTTPS and point APP_URL/site_url to the final public URL.') }}</li>
            <li>{{ __('Configure cron or use the manual sync button for pack format updates (optional).') }}</li>
            <li>{{ __('Set up regular backups of the database and the private storage directory.') }}</li>
        </ul>
    </div>
@endsection
