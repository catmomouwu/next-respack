@extends('layouts.admin')

@section('title', __('Users'))
@section('heading', __('Users'))
@section('content')
    <form method="GET" action="{{ route('admin.users.index') }}" class="nr-card p-4">
        <div class="grid gap-3 sm:grid-cols-[1.5fr_1fr_1fr_auto]">
            <input class="nr-input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Username or email…') }}">
            <select class="nr-input" name="role">
                <option value="">{{ __('All roles') }}</option>
                @foreach (['user', 'admin', 'super_admin'] as $role)
                    <option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>
                @endforeach
            </select>
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
                        <th class="nr-th">{{ __('User') }}</th>
                        <th class="nr-th">{{ __('Role') }}</th>
                        <th class="nr-th">{{ __('Status') }}</th>
                        <th class="nr-th">{{ __('Storage') }}</th>
                        <th class="nr-th">{{ __('Joined') }}</th>
                        <th class="nr-th"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="nr-td">
                                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $user->displayName() }}</p>
                                <p class="font-mono text-xs text-slate-500">{{ $user->email }}</p>
                            </td>
                            <td class="nr-td"><span class="nr-badge-slate">{{ $user->role->value }}</span></td>
                            <td class="nr-td">
                                @if ($user->is_active)
                                    <span class="nr-badge-green">{{ __('Active') }}</span>
                                @else
                                    <span class="nr-badge-red">{{ __('Disabled') }}</span>
                                @endif
                            </td>
                            <td class="nr-td text-xs">{{ nr_bytes((int) ($user->quota?->used_bytes ?? 0)) }} / {{ nr_bytes((int) $user->storage_quota) }}</td>
                            <td class="nr-td text-xs text-slate-500">{{ $user->created_at?->format('Y-m-d') }}</td>
                            <td class="nr-td text-right"><a href="{{ route('admin.users.show', $user) }}" class="nr-link text-xs">{{ __('Manage') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="nr-td py-8 text-center text-sm text-slate-500">{{ __('No users found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-4">{{ $users->links() }}</div>
    </div>
@endsection
