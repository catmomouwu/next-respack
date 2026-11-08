@extends('layouts.admin')

@section('title', __('Storage'))
@section('heading', __('Storage'))
@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="nr-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Active backend') }}</p>
            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100">{{ ['s3' => 'S3', 'webdav' => 'WebDAV'][$driver] ?? __('Local') }}</p>
        </div>
        <div class="nr-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Files') }}</p>
            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100">{{ number_format($usage['files']) }}</p>
        </div>
        <div class="nr-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Stored bytes') }}</p>
            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100">{{ nr_bytes($usage['bytes']) }}</p>
        </div>
        <div class="nr-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Free disk space') }}</p>
            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100">{{ $localFree !== null ? nr_bytes((int) $localFree) : '—' }}</p>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="nr-card p-5" x-data="{ driver: '{{ old('target_driver', $settings['driver'] === 'local' ? 's3' : 'local') }}', testing: false, result: null }">
            <p class="nr-section-title">{{ __('Storage configuration & migration') }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Existing versions keep their current backend until a migration copies and verifies every file.') }}</p>

            <form method="POST" action="{{ route('admin.storage.plan') }}" id="storage-plan-form" class="mt-4 space-y-3">
                @csrf
                <div class="grid gap-2 sm:grid-cols-3">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700" :class="driver === 'local' ? 'border-emerald-500' : ''">
                        <input type="radio" name="target_driver" value="local" x-model="driver" class="text-emerald-600">
                        {{ __('Local path') }}
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700" :class="driver === 's3' ? 'border-emerald-500' : ''">
                        <input type="radio" name="target_driver" value="s3" x-model="driver" class="text-emerald-600">
                        S3
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700" :class="driver === 'webdav' ? 'border-emerald-500' : ''">
                        <input type="radio" name="target_driver" value="webdav" x-model="driver" class="text-emerald-600">
                        WebDAV
                    </label>
                </div>

                <div x-show="driver === 'local'">
                    <label class="nr-label">{{ __('Local directory') }}</label>
                    <input class="nr-input font-mono text-xs" name="local_path" value="{{ $settings['local_path'] }}">
                    <p class="nr-help">{{ __('Saved as storage_local_path after migration. Must be writable and outside the public web root.') }}</p>
                </div>

                <div x-show="driver === 's3'" x-cloak class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="nr-label">{{ __('Endpoint') }}</label>
                        <input class="nr-input" name="s3_endpoint" value="{{ $settings['s3_endpoint'] }}" placeholder="https://s3.example.com">
                    </div>
                    <div>
                        <label class="nr-label">{{ __('Region') }}</label>
                        <input class="nr-input" name="s3_region" value="{{ $settings['s3_region'] }}">
                    </div>
                    <div>
                        <label class="nr-label">{{ __('Bucket') }}</label>
                        <input class="nr-input" name="s3_bucket" value="{{ $settings['s3_bucket'] }}">
                    </div>
                    <div>
                        <label class="nr-label">{{ __('Access key') }}</label>
                        <input class="nr-input" name="s3_access_key" value="{{ $settings['s3_access_key'] }}" autocomplete="off">
                    </div>
                    <div>
                        <label class="nr-label">{{ __('Secret key') }}</label>
                        <input class="nr-input" name="s3_secret_key" type="password" value="" placeholder="{{ $settings['secret_set'] ? __('Leave blank to keep the saved secret') : '' }}" autocomplete="new-password">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="nr-label">{{ __('Path prefix') }}</label>
                        <input class="nr-input" name="s3_prefix" value="{{ $settings['s3_prefix'] }}">
                    </div>
                </div>

                <div x-show="driver === 'webdav'" x-cloak class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="nr-label">{{ __('WebDAV URL') }}</label>
                        <input class="nr-input" name="webdav_url" value="{{ $settings['webdav_url'] }}" placeholder="https://dav.example.com/remote.php/dav/files/user">
                        <p class="nr-help">{{ __('The collection that stores the pack files. Subdirectories are created automatically.') }}</p>
                    </div>
                    <div>
                        <label class="nr-label">{{ __('Username') }}</label>
                        <input class="nr-input" name="webdav_username" value="{{ $settings['webdav_username'] }}" autocomplete="off">
                    </div>
                    <div>
                        <label class="nr-label">{{ __('Password') }}</label>
                        <input class="nr-input" name="webdav_password" type="password" value="" placeholder="{{ $settings['webdav_password_set'] ? __('Leave blank to keep the saved password') : '' }}" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="nr-label">{{ __('Path prefix') }}</label>
                        <input class="nr-input" name="webdav_prefix" value="{{ $settings['webdav_prefix'] }}" placeholder="next-respack">
                    </div>
                    <div>
                        <label class="nr-label">{{ __('TLS verification') }}</label>
                        <input type="hidden" name="webdav_verify_ssl" value="0">
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="webdav_verify_ssl" value="1" class="rounded text-emerald-600" @checked($settings['webdav_verify_ssl'])>
                            {{ __('Verify the remote TLS certificate') }}
                        </label>
                        <p class="nr-help">{{ __('Disable only for a server with a self-signed certificate.') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="nr-btn-secondary" :disabled="testing"
                            @click="
                                testing = true; result = null;
                                const form = document.getElementById('storage-plan-form');
                                fetch('{{ route('admin.storage.test') }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
                                    body: new FormData(form),
                                }).then(async (response) => {
                                    const data = await response.json().catch(() => ({ ok: false, errors: ['{{ __('Unexpected response') }}'] }));
                                    result = data;
                                }).catch((error) => { result = { ok: false, errors: [error.message] }; })
                                .finally(() => { testing = false; });
                            ">
                        <span x-show="!testing">{{ __('Test configuration') }}</span>
                        <span x-show="testing" x-cloak>{{ __('Testing…') }}</span>
                    </button>
                    <button type="submit" class="nr-btn-primary">{{ __('Create migration plan') }}</button>
                </div>

                <div class="space-y-2" x-show="result !== null" x-cloak>
                    <div class="nr-success" x-show="result?.ok">{{ __('The target storage is writable and readable.') }}</div>
                    <div class="nr-error" x-show="result && !result.ok">
                        <template x-for="error in (result?.errors || [])" :key="error"><p x-text="error"></p></template>
                    </div>
                </div>
            </form>
        </div>

        <div class="nr-card p-5">
            <p class="nr-section-title">{{ __('Maintenance') }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Clean local files that do not belong to any database record. A file is only removed after the version record is truly gone (including soft-deleted versions).') }}</p>
            <form method="POST" action="{{ route('admin.storage.orphans') }}" class="mt-4" onsubmit="return confirm('{{ __('Scan and delete orphan local files? Keep a backup first.') }}')">
                @csrf
                <button class="nr-btn-secondary" type="submit" @disabled($driver !== 'local')>{{ __('Clean orphan files') }}</button>
            </form>
            @if ($driver !== 'local')
                <p class="nr-warning mt-3 text-xs">{{ __('Orphan cleanup is implemented for local storage only.') }}</p>
            @endif

            <div class="mt-6 border-t border-slate-100 pt-4 dark:border-slate-800">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Current local path') }}</p>
                <p class="mt-1 break-all font-mono text-xs text-slate-500 dark:text-slate-400">{{ $settings['local_path'] }}</p>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Local bytes: :local · S3 bytes: :s3 · WebDAV bytes: :webdav', ['local' => nr_bytes($usage['local_bytes']), 's3' => nr_bytes($usage['s3_bytes']), 'webdav' => nr_bytes($usage['webdav_bytes'])]) }}</p>
            </div>
        </div>
    </div>

    <div class="nr-card mt-4 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <p class="nr-section-title !text-base">{{ __('Migration history') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="nr-table">
                <thead>
                    <tr>
                        <th class="nr-th">{{ __('From') }}</th>
                        <th class="nr-th">{{ __('To') }}</th>
                        <th class="nr-th">{{ __('Status') }}</th>
                        <th class="nr-th">{{ __('Files') }}</th>
                        <th class="nr-th">{{ __('Started') }}</th>
                        <th class="nr-th"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($migrations as $migration)
                        <tr>
                            <td class="nr-td">{{ $migration->from_driver }}</td>
                            <td class="nr-td">{{ $migration->to_driver }}</td>
                            <td class="nr-td">
                                <span class="nr-badge {{ in_array($migration->status, ['completed']) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : (in_array($migration->status, ['failed']) ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300') }}">{{ $migration->status }}</span>
                                @if ($migration->error)
                                    <p class="mt-1 max-w-xs text-[11px] text-red-600 dark:text-red-400">{{ $migration->error }}</p>
                                @endif
                            </td>
                            <td class="nr-td text-xs">{{ $migration->stats['copied_files'] ?? ($migration->stats['files'] ?? '—') }}</td>
                            <td class="nr-td text-xs text-slate-500">{{ $migration->started_at?->format('Y-m-d H:i') ?? $migration->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="nr-td text-right">
                                @if (in_array($migration->status, ['planned', 'failed']))
                                    <form method="POST" action="{{ route('admin.storage.run', $migration) }}" onsubmit="return confirm('{{ __('Run this migration now? Large datasets can take a long time.') }}')">
                                        @csrf
                                        <button class="nr-btn-primary !px-2.5 !py-1.5 text-xs" type="submit">{{ $migration->status === 'failed' ? __('Retry') : __('Run migration') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="nr-td py-8 text-center text-sm text-slate-500">{{ __('No migrations yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
