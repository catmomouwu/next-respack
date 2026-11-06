@extends('layouts.admin')

@section('title', __('Pack format data'))
@section('heading', __('Pack format data'))
@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['label' => __('Formats'), 'value' => $stats['formats']],
            ['label' => __('Mapped versions'), 'value' => $stats['versions']],
            ['label' => __('Releases'), 'value' => $stats['releases']],
            ['label' => __('Snapshots'), 'value' => $stats['snapshots']],
            ['label' => __('Manual records'), 'value' => $stats['manual']],
        ] as $card)
            <div class="nr-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ number_format($card['value']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="nr-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="nr-section-title">{{ __('Synchronization') }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Dataset') }}: <span class="font-mono">{{ $datasetVersion ?? __('bundled / none') }}</span>
                    </p>
                </div>
                @if ($isDue)
                    <span class="nr-badge-amber">{{ __('Update due') }}</span>
                @else
                    <span class="nr-badge-green">{{ __('Up to date') }}</span>
                @endif
            </div>

            <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                {{ __('Synchronization fetches the Minecraft Wiki pack format page through the MediaWiki API, parses the complete version table and validates the result before applying it. A failed sync never overwrites the current data.') }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('admin.pack-formats.sync') }}">
                    @csrf
                    <button type="submit" class="nr-btn-primary">{{ __('Synchronize now') }}</button>
                </form>
                <a href="{{ config('nextrespack.pack_format_source_url') }}" target="_blank" rel="noopener" class="nr-btn-ghost">{{ __('Open source page') }}</a>
            </div>

            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Last successful sync') }}:
                {{ $lastSync?->finished_at?->format('Y-m-d H:i') ?? __('never') }}
                @if ($lastSync)
                    · {{ __(':count records', ['count' => ($lastSync->records_added ?? 0) + ($lastSync->records_updated ?? 0)]) }}
                @endif
            </p>

            <p class="mt-3 border-t border-slate-100 pt-3 text-[11px] text-slate-500 dark:border-slate-800 dark:text-slate-400">
                {{ __('Cron is optional. On shared hosting, set a daily cron job if available, or use this manual button. Auto-sync is additionally triggered at most once per day by admin visits and never on ordinary page views.') }}
            </p>
        </div>

        <div class="nr-card p-5" x-data="{ tab: 'json' }">
            <p class="nr-section-title">{{ __('Import JSON / CSV') }}</p>
            <div class="mt-3 flex gap-2">
                <button type="button" class="nr-btn-secondary !py-1.5 text-xs" :class="tab === 'json' ? '!bg-emerald-600 !text-white' : ''" @click="tab = 'json'">JSON</button>
                <button type="button" class="nr-btn-secondary !py-1.5 text-xs" :class="tab === 'csv' ? '!bg-emerald-600 !text-white' : ''" @click="tab = 'csv'">CSV</button>
            </div>

            <form method="POST" action="{{ route('admin.pack-formats.import') }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="format_type" :value="tab">
                <div>
                    <label class="nr-label">{{ __('Paste data') }}</label>
                    <textarea class="nr-input font-mono text-xs" name="payload" rows="7"
                        x-bind:placeholder="tab === 'json' ? '{\"records\":[{\"version_name\":\"1.21.9\",\"version_type\":\"release\",\"format_value\":\"69.0\"}]}' : 'version_name,version_type,format_value,release_date\n1.21.9,release,69.0,2025-10-01'"></textarea>
                </div>
                <div>
                    <label class="nr-label">{{ __('Or upload a file (overrides pasted data)') }}</label>
                    <input class="nr-input" type="file" name="file" accept=".json,.csv,text/csv,application/json">
                </div>
                <p class="nr-help">{{ __('JSON accepts a "records" array or a bare array. CSV requires version_name; optional: version_type, format_value, format_major, format_minor, release_date.') }}</p>
                <button class="nr-btn-primary" type="submit">{{ __('Import data') }}</button>
            </form>
        </div>
    </div>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <p class="nr-section-title !text-base">{{ __('Recent records') }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Manual corrections are preserved across synchronization unless the upstream record changes.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th">{{ __('Version') }}</th>
                        <th class="nr-th">{{ __('Type') }}</th>
                        <th class="nr-th">{{ __('Format') }}</th>
                        <th class="nr-th">{{ __('Release date') }}</th>
                        <th class="nr-th">{{ __('Source') }}</th>
                        <th class="nr-th"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent as $record)
                        <tr>
                            <td class="nr-td font-mono text-xs">{{ $record->version_name }}</td>
                            <td class="nr-td"><span class="nr-badge-slate">{{ $record->version_type }}</span></td>
                            <td class="nr-td font-mono text-xs">{{ $record->format?->format_value ?? '—' }}</td>
                            <td class="nr-td text-xs">{{ $record->release_date?->toDateString() ?? '—' }}</td>
                            <td class="nr-td text-xs">
                                @if ($record->is_manual)
                                    <span class="nr-badge-amber">{{ __('manual') }}</span>
                                @else
                                    <span class="nr-badge-slate">{{ __('synced') }}</span>
                                @endif
                            </td>
                            <td class="nr-td text-right"><a href="{{ route('admin.pack-formats.edit', $record) }}" class="nr-link text-xs">{{ __('Correct') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="nr-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <p class="nr-section-title !text-base">{{ __('Sync logs') }}</p>
                <a href="{{ route('admin.pack-formats.history') }}" class="nr-link text-xs">{{ __('History') }} →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="nr-table">
                    <thead>
                        <tr>
                            <th class="nr-th">{{ __('Status') }}</th>
                            <th class="nr-th">{{ __('Trigger') }}</th>
                            <th class="nr-th">{{ __('Changes') }}</th>
                            <th class="nr-th">{{ __('Finished') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="nr-td">
                                    <span class="nr-badge {{ $log->status === 'success' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : ($log->status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-800') }}">{{ $log->status }}</span>
                                    @if ($log->error)
                                        <p class="mt-1 max-w-xs text-[11px] text-red-600 dark:text-red-400">{{ $log->error }}</p>
                                    @endif
                                </td>
                                <td class="nr-td text-xs">{{ $log->trigger }}</td>
                                <td class="nr-td text-xs">+{{ $log->records_added }} / ~{{ $log->records_updated }} / -{{ $log->records_removed }}</td>
                                <td class="nr-td text-xs text-slate-500">{{ $log->finished_at?->format('Y-m-d H:i') ?? $log->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="nr-td py-8 text-center text-sm text-slate-500">{{ __('No sync runs yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 pb-4">{{ $logs->links() }}</div>
        </div>

        <div class="nr-card p-5">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base">{{ __('Latest snapshots') }}</p>
                <a href="{{ route('admin.pack-formats.history') }}" class="nr-link text-xs">{{ __('All snapshots') }}</a>
            </div>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($history as $item)
                    <div class="flex items-center justify-between gap-3 py-2.5 text-xs">
                        <div>
                            <p class="font-medium text-slate-700 dark:text-slate-300">{{ $item->note ?: __('Automatic snapshot') }}</p>
                            <p class="text-slate-400">{{ $item->dataset_version }} · {{ $item->created_at?->format('Y-m-d H:i') }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.pack-formats.rollback', $item) }}" onsubmit="return confirm('{{ __('Restore this snapshot? The current data will be snapshotted first.') }}')">
                            @csrf
                            <button class="nr-btn-secondary !px-2 !py-1 text-[11px]" type="submit">{{ __('Restore') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">{{ __('No snapshots yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
