@extends('layouts.admin')

@section('title', $user->displayName())
@section('heading', __('User') . ': ' . $user->displayName())
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.users.index') }}" class="nr-btn-ghost">{{ __('← Back to users') }}</a>
        @if ($user->id !== auth()->id())
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('{{ __('Delete this user? Their packs will be disabled but files remain until removed.') }}')">
                @csrf
                @method('DELETE')
                <button class="nr-btn-danger" type="submit">{{ __('Delete user') }}</button>
            </form>
        @endif
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="nr-card p-5 lg:col-span-2">
            <p class="nr-section-title !text-base">{{ __('Account') }}</p>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-slate-500">{{ __('Username') }}</dt><dd class="font-medium">{{ $user->username }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('Email') }}</dt><dd class="break-all font-mono text-xs">{{ $user->email }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('Email verified') }}</dt><dd>{{ $user->email_verified_at?->format('Y-m-d H:i') ?? __('No') }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('Joined') }}</dt><dd>{{ $user->created_at?->format('Y-m-d H:i') }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('Storage used') }}</dt><dd>{{ nr_bytes((int) ($user->quota?->used_bytes ?? 0)) }}</dd></div>
                <div><dt class="text-xs text-slate-500">{{ __('Packs') }}</dt><dd>{{ $packs->count() }}</dd></div>
            </dl>
        </div>

        <div class="nr-card p-5">
            <p class="nr-section-title !text-base">{{ __('Permissions') }}</p>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-3 space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="nr-label" for="role">{{ __('Role') }}</label>
                    <select class="nr-input" id="role" name="role">
                        @foreach (['user', 'admin', 'super_admin'] as $role)
                            <option value="{{ $role }}" @selected(old('role', $user->role->value) === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="nr-label" for="storage_quota_mb">{{ __('Storage quota (MB)') }}</label>
                    <input class="nr-input" id="storage_quota_mb" name="storage_quota_mb" type="number" min="1" value="{{ old('storage_quota_mb', (int) round($user->storage_quota / 1024 / 1024)) }}">
                </div>
                <div>
                    <label class="nr-label" for="is_active">{{ __('Status') }}</label>
                    <select class="nr-input" id="is_active" name="is_active">
                        <option value="1" @selected(old('is_active', (int) $user->is_active) == 1)>{{ __('Active') }}</option>
                        <option value="0" @selected(old('is_active', (int) $user->is_active) == 0)>{{ __('Disabled') }}</option>
                    </select>
                </div>
                <button class="nr-btn-primary w-full" type="submit">{{ __('Save changes') }}</button>
            </form>
        </div>
    </div>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <p class="nr-section-title !text-base">{{ __('Resource packs') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th">{{ __('Name') }}</th>
                        <th class="nr-th">{{ __('Format') }}</th>
                        <th class="nr-th">{{ __('Size') }}</th>
                        <th class="nr-th">{{ __('Status') }}</th>
                        <th class="nr-th">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packs as $pack)
                        <tr>
                            <td class="nr-td"><a href="{{ route('admin.packs.index', ['q' => $pack->name]) }}" class="nr-link">{{ $pack->name }}</a></td>
                            <td class="nr-td font-mono text-xs">{{ $pack->displayFormat() }}</td>
                            <td class="nr-td text-xs">{{ nr_bytes((int) $pack->totalSize()) }}</td>
                            <td class="nr-td"><span class="nr-badge-slate">{{ $pack->status }}</span></td>
                            <td class="nr-td text-xs text-slate-500">{{ $pack->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="nr-td py-8 text-center text-sm text-slate-500">{{ __('No packs.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="nr-card mt-4 p-5">
        <p class="nr-section-title !text-base">{{ __('Recent downloads') }}</p>
        <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($downloads as $download)
                <div class="flex items-center justify-between gap-3 py-2.5 text-xs">
                    <div class="min-w-0">
                        <p class="text-slate-700 dark:text-slate-300">{{ $download->pack?->name }} · {{ __('v:number', ['number' => $download->version?->version_number ?? '—']) }}</p>
                        <p class="text-slate-400">{{ $download->created_at?->format('Y-m-d H:i') }}</p>
                    </div>
                    <span class="nr-badge-slate">{{ $download->status_code }}</span>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">{{ __('No downloads.') }}</p>
            @endforelse
        </div>
    </div>
@endsection
