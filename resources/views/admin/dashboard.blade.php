@extends('layouts.admin')

@section('title', __('Overview'))
@section('heading', __('Overview'))
@section('content')
    @php($maxDaily = max(1, collect($chart)->max('total')))

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => __('Users'), 'value' => number_format($stats['users']), 'hint' => __('registered accounts')],
            ['label' => __('Active packs'), 'value' => number_format($stats['packs']), 'hint' => __('published resource packs')],
            ['label' => __('Downloads'), 'value' => number_format($stats['downloads']), 'hint' => __(':count in the last 30 days', ['count' => number_format($stats['downloads_30d'])])],
            ['label' => __('Private storage'), 'value' => nr_bytes($stats['storage']), 'hint' => __('across all versions')],
        ] as $card)
            <div class="nr-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="nr-card p-5 lg:col-span-2">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base">{{ __('Downloads · last 14 days') }}</p>
                <span class="nr-badge-slate">{{ array_sum(array_column($chart, 'total')) }}</span>
            </div>
            <div class="mt-6 flex h-40 items-end gap-1.5">
                @foreach ($chart as $bar)
                    <div class="group relative flex h-full flex-1 items-end" title="{{ $bar['day'] }}: {{ $bar['total'] }}">
                        <div class="w-full rounded-t bg-emerald-500/80 transition group-hover:bg-emerald-500 dark:bg-emerald-400/70" style="height: {{ max(3, round($bar['total'] / $maxDaily * 100)) }}%"></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="nr-card p-5">
            <p class="nr-section-title !text-base">{{ __('Pack format dataset') }}</p>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ number_format($stats['formats']) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('format records') }}</p>
            <div class="mt-4 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                <p class="text-slate-500 dark:text-slate-400">{{ __('Last sync') }}</p>
                <p class="mt-1 font-medium text-slate-700 dark:text-slate-300">
                    {{ $lastSync?->created_at?->format('Y-m-d H:i') ?? __('never') }}
                    @if ($lastSync)
                        <span class="nr-badge {{ $lastSync->status === 'success' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' }}">{{ $lastSync->status }}</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.pack-formats.index') }}" class="nr-btn-secondary mt-4 w-full !py-2 text-xs">{{ __('Manage pack formats') }}</a>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="nr-card p-5">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base">{{ __('Recent resource packs') }}</p>
                <a href="{{ route('admin.packs.index') }}" class="nr-link text-xs">{{ __('All packs') }} →</a>
            </div>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($recentPacks as $pack)
                    <div class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $pack->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $pack->user?->displayName() ?? __('Guest') }} · {{ nr_bytes((int) ($pack->currentVersion?->file_size ?? 0)) }}</p>
                        </div>
                        <span class="nr-badge shrink-0 {{ $pack->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $pack->status }}</span>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No packs yet.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="nr-card p-5">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base">{{ __('Recent activity') }}</p>
                <a href="{{ route('admin.logs.index') }}" class="nr-link text-xs">{{ __('All logs') }} →</a>
            </div>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($recentAudit as $log)
                    <div class="py-2.5">
                        <p class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $log->description ?: $log->action }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ $log->actor?->displayName() ?? __('system') }} · {{ $log->created_at?->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No audit entries yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="nr-card mt-4 p-5">
        <div class="flex items-center justify-between">
            <p class="nr-section-title !text-base">{{ __('Newest users') }}</p>
            <a href="{{ route('admin.users.index') }}" class="nr-link text-xs">{{ __('All users') }} →</a>
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th">{{ __('Username') }}</th>
                        <th class="nr-th">{{ __('Email') }}</th>
                        <th class="nr-th">{{ __('Role') }}</th>
                        <th class="nr-th">{{ __('Joined') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentUsers as $user)
                        <tr>
                            <td class="nr-td"><a href="{{ route('admin.users.show', $user) }}" class="nr-link">{{ $user->displayName() }}</a></td>
                            <td class="nr-td font-mono text-xs">{{ $user->email }}</td>
                            <td class="nr-td"><span class="nr-badge-slate">{{ $user->role->value }}</span></td>
                            <td class="nr-td text-xs text-slate-500">{{ $user->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
