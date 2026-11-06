@extends('layouts.app')

@section('title', __('Dashboard'))
@section('content')
    @php
        $maxDaily = max(1, collect($chart)->max('total'));
        $used = (int) $usage['used'];
        $quota = (int) $usage['limit'];
        $percent = (int) $usage['percent'];
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Dashboard') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Overview of your resource packs, storage and downloads.') }}</p>
        </div>
        <a href="{{ route('packs.create') }}" class="nr-btn-primary">{{ __('Upload resource pack') }}</a>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="nr-card p-5 lg:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Storage usage') }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ nr_bytes($used) }} <span class="text-sm font-medium text-slate-500 dark:text-slate-400">/ {{ nr_bytes($quota) }}</span></p>
                </div>
                <span class="nr-badge {{ $percent >= 90 ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : ($percent >= 70 ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300') }}">{{ $percent }}%</span>
            </div>
            <div class="nr-progress mt-4">
                <div class="nr-progress-bar" style="width: {{ $percent }}%"></div>
            </div>
            <div class="mt-3 flex flex-wrap justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
                <span>{{ __(':count resource packs', ['count' => $usage['count'] ?? $packCount]) }}</span>
                <span>{{ __(':free free', ['free' => nr_bytes(max(0, $quota - $used))]) }}</span>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            <div class="nr-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Total downloads') }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ number_format($totalDownloads) }}</p>
            </div>
            <div class="nr-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Resource packs') }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ number_format($packCount) }}</p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="nr-card p-5">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base">{{ __('Downloads · last 14 days') }}</p>
                <span class="nr-badge-slate">{{ array_sum(array_column($chart, 'total')) }}</span>
            </div>
            <div class="mt-6 flex h-36 items-end gap-1.5">
                @foreach ($chart as $bar)
                    <div class="group relative flex h-full flex-1 items-end" title="{{ $bar['day'] }}: {{ $bar['total'] }}">
                        <div class="w-full rounded-t bg-emerald-500/80 transition group-hover:bg-emerald-500 dark:bg-emerald-400/70" style="height: {{ max(3, round($bar['total'] / $maxDaily * 100)) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[10px] text-slate-400 dark:text-slate-500">
                <span>{{ $chart[0]['day'] ?? '' }}</span>
                <span>{{ $chart[count($chart) - 1]['day'] ?? '' }}</span>
            </div>
        </div>

        <div class="nr-card p-5">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base">{{ __('Recent resource packs') }}</p>
                <a href="{{ route('packs.index') }}" class="nr-link text-xs">{{ __('View all') }} →</a>
            </div>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($packs as $pack)
                    <a href="{{ route('packs.show', $pack) }}" class="flex items-center justify-between gap-3 py-3 hover:opacity-80">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $pack->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $pack->currentVersion?->formatLabel() ? __('Format') . ' ' . $pack->currentVersion->formatLabel() : __('No version') }} · {{ nr_bytes((int) ($pack->currentVersion?->file_size ?? 0)) }}</p>
                        </div>
                        <span class="nr-badge {{ $pack->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                            {{ $pack->status === 'active' ? __('Active') : __('Disabled') }}
                        </span>
                    </a>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('You have not uploaded any resource packs yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
