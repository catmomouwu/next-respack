@extends('layouts.admin')

@section('title', __('Audit & download logs'))
@section('heading', __('Audit & download logs'))
@section('content')
    <form method="GET" action="{{ route('admin.logs.index') }}" class="nr-card p-4">
        <div class="grid gap-3 sm:grid-cols-[1.6fr_1fr_auto]">
            <input class="nr-input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Action, description or username…') }}">
            <select class="nr-input" name="action">
                <option value="">{{ __('All actions') }}</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
            <button class="nr-btn-primary" type="submit">{{ __('Filter') }}</button>
        </div>
    </form>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <p class="nr-section-title !text-base">{{ __('Audit trail') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th">{{ __('Action') }}</th>
                        <th class="nr-th">{{ __('Description') }}</th>
                        <th class="nr-th">{{ __('Actor') }}</th>
                        <th class="nr-th">{{ __('IP hash') }}</th>
                        <th class="nr-th">{{ __('Time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="nr-td font-mono text-xs">{{ $log->action }}</td>
                            <td class="nr-td">
                                <p class="text-sm">{{ $log->description }}</p>
                                @if ($log->metadata)
                                    <p class="mt-1 break-all text-[11px] text-slate-400">{{ \Illuminate\Support\Str::limit(json_encode($log->metadata, JSON_UNESCAPED_UNICODE), 140) }}</p>
                                @endif
                            </td>
                            <td class="nr-td text-xs">{{ $log->actor?->displayName() ?? __('system') }}</td>
                            <td class="nr-td font-mono text-[11px] text-slate-400">{{ $log->ip_hash ? substr($log->ip_hash, 0, 12).'…' : '—' }}</td>
                            <td class="nr-td text-xs text-slate-500">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="nr-td py-8 text-center text-sm text-slate-500">{{ __('No audit entries.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4">{{ $logs->links() }}</div>
    </div>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <p class="nr-section-title !text-base">{{ __('Download log') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th">{{ __('Pack') }}</th>
                        <th class="nr-th">{{ __('Version') }}</th>
                        <th class="nr-th">{{ __('Method') }}</th>
                        <th class="nr-th">{{ __('Status') }}</th>
                        <th class="nr-th">{{ __('Range') }}</th>
                        <th class="nr-th">{{ __('Bytes') }}</th>
                        <th class="nr-th">{{ __('Time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($downloads as $download)
                        <tr>
                            <td class="nr-td text-xs">{{ $download->pack?->name ?? '—' }}</td>
                            <td class="nr-td text-xs">{{ $download->version?->version_number ?? '—' }}</td>
                            <td class="nr-td font-mono text-xs">{{ $download->method }}</td>
                            <td class="nr-td"><span class="nr-badge-slate">{{ $download->status_code }}</span></td>
                            <td class="nr-td font-mono text-[11px]">{{ $download->range_header ?: '—' }}</td>
                            <td class="nr-td text-xs">{{ nr_bytes((int) $download->bytes_sent) }}</td>
                            <td class="nr-td text-xs text-slate-500">{{ $download->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="nr-td py-8 text-center text-sm text-slate-500">{{ __('No downloads recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4">{{ $downloads->links() }}</div>
    </div>
@endsection
