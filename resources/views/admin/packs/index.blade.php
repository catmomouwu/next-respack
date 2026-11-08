@extends('layouts.admin')

@section('title', __('Resource packs'))
@section('heading', __('Resource packs'))
@section('content')
    <form method="GET" action="{{ route('admin.packs.index') }}" class="nr-card p-4">
        <div class="grid gap-3 sm:grid-cols-[1.6fr_1fr_auto]">
            <input class="nr-input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Pack name, owner username or email…') }}">
            <select class="nr-input" name="status">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="disabled" @selected(request('status') === 'disabled')>{{ __('Disabled') }}</option>
            </select>
            <button class="nr-btn-primary" type="submit">{{ __('Filter') }}</button>
        </div>
    </form>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th">{{ __('Pack') }}</th>
                        <th class="nr-th">{{ __('Owner') }}</th>
                        <th class="nr-th">{{ __('Format') }}</th>
                        <th class="nr-th">{{ __('Versions') }}</th>
                        <th class="nr-th">{{ __('Size') }}</th>
                        <th class="nr-th">{{ __('Status') }}</th>
                        <th class="nr-th"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packs as $pack)
                        <tr>
                            <td class="nr-td">
                                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $pack->name }}</p>
                                <p class="text-[11px] text-slate-500">{{ $pack->created_at?->format('Y-m-d H:i') }}</p>
                            </td>
                            <td class="nr-td text-xs">
                                @if ($pack->user)
                                    <a href="{{ route('admin.users.show', $pack->user) }}" class="nr-link">{{ $pack->user->displayName() }}</a>
                                @else
                                    <span class="text-amber-600 dark:text-amber-400">{{ __('Guest') }}</span>
                                @endif
                            </td>
                            <td class="nr-td font-mono text-xs">{{ $pack->displayFormat() }}</td>
                            <td class="nr-td text-xs">{{ $pack->versions_count }}</td>
                            <td class="nr-td text-xs">{{ nr_bytes((int) $pack->totalSize()) }}</td>
                            <td class="nr-td">
                                <span class="nr-badge {{ $pack->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $pack->status }}</span>
                            </td>
                            <td class="nr-td">
                                <div class="flex justify-end gap-1.5">
                                    <form method="POST" action="{{ route('admin.packs.toggle', $pack) }}">
                                        @csrf
                                        <button class="nr-btn-secondary !px-2 !py-1 text-[11px]" type="submit">{{ $pack->status === 'active' ? __('Disable') : __('Enable') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.packs.destroy', $pack) }}" onsubmit="return confirm('{{ __('Delete this pack and its files?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="nr-btn-danger !px-2 !py-1 text-[11px]" type="submit">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="nr-td py-8 text-center text-sm text-slate-500">{{ __('No resource packs found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4">{{ $packs->links() }}</div>
    </div>
@endsection
