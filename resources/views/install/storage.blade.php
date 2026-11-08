@extends('layouts.install')

@section('title', __('Storage backend'))
@section('heading', __('Storage backend'))
@section('subheading', __('Choose where private resource pack ZIP files are stored. Files are never served directly from disk.'))

@section('steps')
    @include('components.install-steps', ['current' => 3])
@endsection

@section('content')
    @php($driver = old('driver', $data['driver'] ?? 'local'))
    <div x-data="{ driver: '{{ $driver }}', testing: false, ok: null, errors: [], warnings: [], free: null }">
        <form method="POST" action="{{ route('install.storage.save') }}" id="storage-form">
            @csrf

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                       :class="driver === 'local' ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-500/10' : 'border-slate-200 dark:border-slate-700'">
                    <input type="radio" name="driver" value="local" x-model="driver" class="mt-0.5 text-emerald-600" @checked($driver === 'local')>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Local storage (recommended)') }}</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ __('Files stay on this server. Works on shared hosting and VPS.') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                       :class="driver === 's3' ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-500/10' : 'border-slate-200 dark:border-slate-700'">
                    <input type="radio" name="driver" value="s3" x-model="driver" class="mt-0.5 text-emerald-600" @checked($driver === 's3')>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('S3-compatible object storage') }}</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ __('Amazon S3, Cloudflare R2 or any compatible provider.') }}</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                       :class="driver === 'webdav' ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-500/10' : 'border-slate-200 dark:border-slate-700'">
                    <input type="radio" name="driver" value="webdav" x-model="driver" class="mt-0.5 text-emerald-600" @checked($driver === 'webdav')>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('WebDAV storage') }}</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ __('Nextcloud, ownCloud, Synology or any WebDAV server.') }}</span>
                    </span>
                </label>
            </div>

            <div class="mt-6" x-show="driver === 'local'">
                <label class="nr-label" for="local_path">{{ __('Storage directory') }}</label>
                <input class="nr-input font-mono text-xs" id="local_path" name="local_path" value="{{ old('local_path', $data['local_path'] ?? config('nextrespack.storage_root')) }}">
                <p class="nr-help">{{ __('Absolute path or a path relative to the project root. Must be outside the public web root. Default: storage/app/private/resource-packs') }}</p>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2" x-show="driver === 's3'" x-cloak>
                <div class="sm:col-span-2">
                    <label class="nr-label" for="endpoint">{{ __('Endpoint (optional)') }}</label>
                    <input class="nr-input" id="endpoint" name="endpoint" value="{{ old('endpoint', $data['endpoint'] ?? '') }}" placeholder="https://s3.example.com">
                </div>
                <div>
                    <label class="nr-label" for="region">{{ __('Region') }}</label>
                    <input class="nr-input" id="region" name="region" value="{{ old('region', $data['region'] ?? 'us-east-1') }}">
                </div>
                <div>
                    <label class="nr-label" for="bucket">{{ __('Bucket') }}</label>
                    <input class="nr-input" id="bucket" name="bucket" value="{{ old('bucket', $data['bucket'] ?? '') }}">
                </div>
                <div>
                    <label class="nr-label" for="access_key">{{ __('Access key') }}</label>
                    <input class="nr-input" id="access_key" name="access_key" value="{{ old('access_key', $data['access_key'] ?? '') }}" autocomplete="off">
                </div>
                <div>
                    <label class="nr-label" for="secret_key">{{ __('Secret key') }}</label>
                    <input class="nr-input" id="secret_key" name="secret_key" type="password" value="{{ old('secret_key', $data['secret_key'] ?? '') }}" autocomplete="new-password">
                </div>
                <div>
                    <label class="nr-label" for="prefix">{{ __('Path prefix (optional)') }}</label>
                    <input class="nr-input" id="prefix" name="prefix" value="{{ old('prefix', $data['prefix'] ?? '') }}" placeholder="next-respack">
                </div>
                <div>
                    <label class="nr-label">{{ __('Path-style endpoint') }}</label>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="path_style" value="1" class="rounded text-emerald-600" @checked(old('path_style', $data['path_style'] ?? true))>
                        {{ __('Use path-style URLs') }}
                    </label>
                    <p class="nr-help">{{ __('Enable for MinIO, Cloudflare R2 and most S3-compatible services.') }}</p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2" x-show="driver === 'webdav'" x-cloak>
                <div class="sm:col-span-2">
                    <label class="nr-label" for="webdav_url">{{ __('WebDAV URL') }}</label>
                    <input class="nr-input" id="webdav_url" name="webdav_url" value="{{ old('webdav_url', $data['webdav_url'] ?? '') }}" placeholder="https://dav.example.com/remote.php/dav/files/user">
                    <p class="nr-help">{{ __('The collection that stores the pack files. Subdirectories are created automatically.') }}</p>
                </div>
                <div>
                    <label class="nr-label" for="webdav_username">{{ __('Username') }}</label>
                    <input class="nr-input" id="webdav_username" name="webdav_username" value="{{ old('webdav_username', $data['webdav_username'] ?? '') }}" autocomplete="off">
                </div>
                <div>
                    <label class="nr-label" for="webdav_password">{{ __('Password') }}</label>
                    <input class="nr-input" id="webdav_password" name="webdav_password" type="password" value="{{ old('webdav_password', $data['webdav_password'] ?? '') }}" autocomplete="new-password">
                </div>
                <div>
                    <label class="nr-label" for="webdav_prefix">{{ __('Path prefix (optional)') }}</label>
                    <input class="nr-input" id="webdav_prefix" name="webdav_prefix" value="{{ old('webdav_prefix', $data['webdav_prefix'] ?? '') }}" placeholder="next-respack">
                </div>
                <div>
                    <label class="nr-label">{{ __('TLS verification') }}</label>
                    <input type="hidden" name="webdav_verify_ssl" value="0">
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="webdav_verify_ssl" value="1" class="rounded text-emerald-600" @checked(old('webdav_verify_ssl', $data['webdav_verify_ssl'] ?? true))>
                        {{ __('Verify the remote TLS certificate') }}
                    </label>
                    <p class="nr-help">{{ __('Disable only for a server with a self-signed certificate.') }}</p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button type="button" class="nr-btn-secondary" :disabled="testing"
                        @click="
                            testing = true; ok = null; errors = []; warnings = [];
                            fetch('{{ route('install.storage.test') }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
                                body: new FormData(document.getElementById('storage-form')),
                            }).then(async (response) => {
                                const data = await response.json().catch(() => ({ ok: false, errors: ['{{ __('Unexpected server response.') }}'] }));
                                ok = Boolean(data.ok);
                                errors = data.errors || [];
                                warnings = data.warnings || [];
                            }).catch((error) => { ok = false; errors = [error.message]; })
                            .finally(() => { testing = false; });
                        ">
                    <span x-show="!testing">{{ __('Test storage') }}</span>
                    <span x-show="testing" x-cloak>{{ __('Testing…') }}</span>
                </button>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('The test writes and deletes a small temporary file.') }}</span>
            </div>

            <div class="mt-4 space-y-2" x-show="ok !== null" x-cloak>
                <div class="nr-success" x-show="ok">{{ __('The storage backend is writable and readable.') }}</div>
                <div class="nr-error" x-show="ok === false">
                    <template x-for="error in errors" :key="error"><p x-text="error"></p></template>
                </div>
                <div class="nr-warning" x-show="warnings.length > 0">
                    <template x-for="warning in warnings" :key="warning"><p x-text="warning"></p></template>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('install.database') }}" class="nr-btn-ghost">{{ __('Back') }}</a>
                <button type="submit" class="nr-btn-primary">{{ __('Save and continue') }}</button>
            </div>
        </form>
    </div>
@endsection
