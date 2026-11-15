@extends('layouts.install')

@section('title', __('Confirm installation'))
@section('heading', __('Confirm installation'))
@section('subheading', __('Review the configuration below. Sensitive values are masked.'))

@section('steps')
    @include('components.install-steps', ['current' => 6])
@endsection

@section('content')
    @php
        $db = $database;
        $st = $storage;
        $si = $site;
    @endphp
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Database') }}</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Driver') }}</dt><dd class="font-medium">{{ strtoupper($db['driver'] ?? '') }}</dd></div>
                @if (($db['driver'] ?? '') !== 'sqlite')
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Host') }}</dt><dd class="font-mono text-xs">{{ $db['host'] ?? '' }}:{{ $db['port'] ?? '' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Database') }}</dt><dd class="font-mono text-xs">{{ $db['database'] ?? '' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Username') }}</dt><dd class="font-mono text-xs">{{ $db['username'] ?? '' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Password') }}</dt><dd class="font-mono text-xs">••••••••</dd></div>
                @else
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('File') }}</dt><dd class="break-all font-mono text-xs">{{ $db['sqlite_path'] ?? '' }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Storage') }}</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Backend') }}</dt><dd class="font-medium">{{ $st['driver'] === 's3' ? 'S3' : __('Local') }}</dd></div>
                @if (($st['driver'] ?? 'local') === 'local')
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Directory') }}</dt><dd class="break-all font-mono text-xs">{{ $st['local_path'] ?? '' }}</dd></div>
                @else
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Bucket') }}</dt><dd class="font-mono text-xs">{{ $st['bucket'] ?? '' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Region') }}</dt><dd class="font-mono text-xs">{{ $st['region'] ?? '' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Secret key') }}</dt><dd class="font-mono text-xs">••••••••</dd></div>
                @endif
            </dl>
        </div>

        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Website') }}</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Name') }}</dt><dd class="text-right font-medium">{{ $si['name'] ?? '' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('URL') }}</dt><dd class="break-all text-right font-mono text-xs">{{ $si['url'] ?? '' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Language') }}</dt><dd>{{ ($si['locale'] ?? 'zh_CN') === 'zh_CN' ? '简体中文' : 'English' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Max upload') }}</dt><dd>{{ $si['max_upload_mb'] ?? '' }} MB</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Registration') }}</dt><dd>{{ ! empty($si['allow_registration']) ? __('Open') : __('Closed') }}</dd></div>
            </dl>
        </div>

        <div class="nr-card-muted p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Administrator') }}</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Username') }}</dt><dd class="font-medium">{{ $admin['username'] ?? '' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Email') }}</dt><dd class="break-all font-mono text-xs">{{ $admin['email'] ?? '' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">{{ __('Role') }}</dt><dd><span class="nr-badge-green">{{ __('Super Administrator') }}</span></dd></div>
            </dl>
        </div>
    </div>

    <div class="nr-warning mt-6">
        {{ __('The installer will run database migrations, create the administrator, initialize storage and import the bundled Minecraft pack format data. This can take a moment. Do not close this page.') }}
    </div>

    <form method="POST" action="{{ route('install.run') }}" class="mt-8" x-data="{ running: false }" @submit="running = true">
        @csrf
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('install.admin') }}" class="nr-btn-ghost">{{ __('Back') }}</a>
            <button type="submit" class="nr-btn-primary !px-6 !py-3" :disabled="running">
                <span x-show="!running">{{ __('Install Next Respack') }}</span>
                <span x-show="running" x-cloak class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                    {{ __('Installing…') }}
                </span>
            </button>
        </div>
    </form>
@endsection
