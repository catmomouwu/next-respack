@extends('layouts.admin')

@section('title', __('Guest uploads'))
@section('heading', __('Guest uploads'))
@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => __('Guest uploads'), 'value' => number_format($stats['total']), 'hint' => __('temporary uploads without an account')],
            ['label' => __('Expired'), 'value' => number_format($stats['expired']), 'hint' => __('waiting for cleanup')],
            ['label' => __('Deletion failures'), 'value' => number_format($stats['failed']), 'hint' => __('queued for retry')],
            ['label' => __('Guest storage'), 'value' => nr_bytes((int) $stats['quota']['used']), 'hint' => $stats['quota']['limit'] > 0 ? __('of :limit site-wide quota', ['limit' => nr_bytes((int) $stats['quota']['limit'])]) : __('unlimited')],
        ] as $card)
            <div class="nr-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="nr-card mt-4 flex flex-wrap items-center justify-between gap-3 p-4">
        <div>
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Manual cleanup') }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('No cron is required. Expired guest files are also removed by throttled requests, but this button runs a larger batch immediately.') }}</p>
        </div>
        <form method="POST" action="{{ route('admin.guests.cleanup') }}">
            @csrf
            <button class="nr-btn-primary" type="submit">{{ __('Run cleanup now') }}</button>
        </form>
    </div>

    <form method="GET" action="{{ route('admin.guests.index') }}" class="nr-card mt-4 p-4">
        <div class="grid gap-3 sm:grid-cols-[1.6fr_1fr_auto]">
            <input class="nr-input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Guest name or original filename…') }}">
            <select class="nr-input" name="status">
                <option value="">{{ __('All guest uploads') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="disabled" @selected(request('status') === 'disabled')">{{ __('Disabled') }}</option>
                <option value="expired" @selected(request('status') === 'expired')>{{ __('Expired') }}</option>
                <option value="failed" @selected(request('status') === 'failed')">{{ __('Deletion failures') }}</option>
            </select>
            <button class="nr-btn-primary" type="submit">{{ __('Filter') }}</button>
        </div>
    </form>

    @if ($failures->isNotEmpty())
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10">
            <p class="text-sm font-semibold text-red-800 dark:text-red-200">{{ __('Deletion failures') }}</p>
            <ul class="mt-2 space-y-1 text-xs text-red-700 dark:text-red-300">
                @foreach ($failures as $failure)
                    <li>
                        <span class="font-semibold">{{ $failure->name }}</span>
                        · {{ __('attempts: :count', ['count' => $failure->delete_attempts]) }}
                        · {{ $failure->delete_attempted_at?->format('Y-m-d H:i') }}
                        · <span class="break-all">{{ $failure->delete_error }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="nr-card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th">{{ __('Guest upload') }}</th>
                        <th class="nr-th">{{ __('Size') }}</th>
                        <th class="nr-th">{{ __('Expires') }}</th>
                        <th class="nr-th">{{ __('Remaining') }}</th>
                        <th class="nr-th">{{ __('Storage') }}</th>
                        <th class="nr-th">{{ __('Status') }}</th>
                        <th class="nr-th"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packs as $pack)
                        @php
                            $remaining = $pack->remainingSeconds();
                            $statusClass = match ($pack->status) {
                                'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                'deletion_failed' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
                                'pending_deletion' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                            };
                        @endphp
                        <tr>
                            <td class="nr-td">
                                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $pack->name }}</p>
                                <p class="text-[11px] text-slate-500">{{ $pack->currentVersion?->original_filename }} · {{ $pack->created_at?->format('Y-m-d H:i') }}</p>
                            </td>
                            <td class="nr-td text-xs">{{ nr_bytes((int) $pack->totalSize()) }}</td>
                            <td class="nr-td text-xs">
                                {{ $pack->guest_expires_at?->format('Y-m-d H:i') ?? '—' }}
                                @if ($pack->isExpired())
                                    <span class="nr-badge-red ml-1">{{ __('Expired') }}</span>
                                @endif
                            </td>
                            <td class="nr-td text-xs">{{ $remaining > 0 ? __(':days days :hours hours', ['days' => intdiv($remaining, 86400), 'hours' => intdiv($remaining % 86400, 3600)]) : '—' }}</td>
                            <td class="nr-td text-xs">
                                <p>{{ $pack->currentVersion?->storage_driver ?? '—' }}</p>
                                <p class="text-[11px] text-slate-500">{{ $pack->versions_count }} {{ __('versions') }}</p>
                            </td>
                            <td class="nr-td">
                                <span class="nr-badge {{ $statusClass }}">{{ $pack->status }}</span>
                            </td>
                            <td class="nr-td">
                                <div class="flex justify-end gap-1.5">
                                    <form method="POST" action="{{ route('admin.guests.toggle', $pack) }}">
                                        @csrf
                                        <button class="nr-btn-secondary !px-2 !py-1 text-[11px]" type="submit">{{ $pack->status === 'active' ? __('Disable download') : __('Enable download') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.guests.destroy', $pack) }}" onsubmit="return confirm('{{ __('Delete this guest upload and its stored file?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="nr-btn-danger !px-2 !py-1 text-[11px]" type="submit">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="nr-td py-8 text-center text-sm text-slate-500">{{ __('No guest uploads found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4">{{ $packs->links() }}</div>
    </div>
@endsection
