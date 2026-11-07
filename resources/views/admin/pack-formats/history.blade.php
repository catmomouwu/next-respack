@extends('layouts.admin')

@section('title', __('Pack format history'))
@section('heading', __('Pack format history'))
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.pack-formats.index') }}" class="nr-btn-ghost">{{ __('← Back to pack formats') }}</a>
        <span class="nr-badge-slate">{{ __('Live dataset: :formats formats / :versions versions', ['formats' => count($live['formats'] ?? []), 'versions' => collect($live['formats'] ?? [])->sum(fn ($f) => count($f['versions'] ?? []))]) }}</span>
    </div>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th">{{ __('Captured') }}</th>
                        <th class="nr-th">{{ __('Dataset') }}</th>
                        <th class="nr-th">{{ __('Note') }}</th>
                        <th class="nr-th">{{ __('Formats') }}</th>
                        <th class="nr-th">{{ __('Versions') }}</th>
                        <th class="nr-th"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $item)
                        @php($snapshot = json_decode((string) $item->snapshot, true))
                        <tr>
                            <td class="nr-td text-xs text-slate-500">{{ $item->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="nr-td font-mono text-[11px]">{{ $item->dataset_version ?? '—' }}</td>
                            <td class="nr-td text-xs">{{ $item->note ?? '—' }}</td>
                            <td class="nr-td text-xs">{{ is_array($snapshot['formats'] ?? null) ? count($snapshot['formats']) : '—' }}</td>
                            <td class="nr-td text-xs">{{ is_array($snapshot['formats'] ?? null) ? collect($snapshot['formats'])->sum(fn ($f) => count($f['versions'] ?? [])) : '—' }}</td>
                            <td class="nr-td text-right">
                                <form method="POST" action="{{ route('admin.pack-formats.rollback', $item) }}" onsubmit="return confirm('{{ __('Restore this snapshot? Current data will be snapshotted first.') }}')">
                                    @csrf
                                    <button class="nr-btn-primary !px-2.5 !py-1.5 text-xs" type="submit">{{ __('Restore') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="nr-td py-8 text-center text-sm text-slate-500">{{ __('No history snapshots.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4">{{ $history->links() }}</div>
    </div>
@endsection
