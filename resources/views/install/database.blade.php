@extends('layouts.install')

@section('title', __('Database configuration'))
@section('heading', __('Database configuration'))
@section('subheading', __('Use an existing database from your hosting panel. The installer only creates Next Respack tables.'))

@section('steps')
    @include('components.install-steps', ['current' => 2])
@endsection

@section('content')
    @php($driver = old('driver', $data['driver'] ?? 'mysql'))
    <div x-data="{ driver: '{{ $driver }}', testing: false, ok: null, errors: [], warnings: [] }">
        <form method="POST" action="{{ route('install.database.save') }}" id="database-form">
            @csrf

            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([
                    'mysql' => __('MySQL 5.7+ (recommended)'),
                    'mariadb' => __('MariaDB (compatibility mode)'),
                    'sqlite' => __('SQLite (light deployment)'),
                ] as $value => $label)
                    <label class="flex cursor-pointer items-start gap-2 rounded-xl border p-3 transition"
                           :class="driver === '{{ $value }}' ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-500/10' : 'border-slate-200 dark:border-slate-700'">
                        <input type="radio" name="driver" value="{{ $value }}" class="mt-0.5 text-emerald-600" x-model="driver" @checked($driver === $value)>
                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2" x-show="driver !== 'sqlite'">
                <div>
                    <label class="nr-label" for="host">{{ __('Database host') }}</label>
                    <input class="nr-input" id="host" name="host" value="{{ old('host', $data['host'] ?? '127.0.0.1') }}" placeholder="127.0.0.1" autocomplete="off">
                </div>
                <div>
                    <label class="nr-label" for="port">{{ __('Port') }}</label>
                    <input class="nr-input" id="port" name="port" type="number" value="{{ old('port', $data['port'] ?? 3306) }}" placeholder="3306">
                </div>
                <div>
                    <label class="nr-label" for="database">{{ __('Database name') }}</label>
                    <input class="nr-input" id="database" name="database" value="{{ old('database', $data['database'] ?? '') }}" autocomplete="off">
                    <p class="nr-help">{{ __('Create the database in your hosting panel first. CREATE DATABASE privilege is not required.') }}</p>
                </div>
                <div>
                    <label class="nr-label" for="username">{{ __('Database username') }}</label>
                    <input class="nr-input" id="username" name="username" value="{{ old('username', $data['username'] ?? '') }}" autocomplete="off">
                </div>
                <div class="sm:col-span-2">
                    <label class="nr-label" for="password">{{ __('Database password') }}</label>
                    <input class="nr-input" id="password" name="password" type="password" value="" placeholder="{{ ($data['password'] ?? '') !== '' ? __('Leave blank to keep the saved password') : '' }}" autocomplete="new-password">
                    <p class="nr-help">{{ __('The password is never displayed after it is saved.') }}</p>
                </div>
                <div>
                    <label class="nr-label" for="charset">{{ __('Charset') }}</label>
                    <input class="nr-input" id="charset" name="charset" value="{{ old('charset', $data['charset'] ?? 'utf8mb4') }}" readonly>
                </div>
                <div>
                    <label class="nr-label" for="collation">{{ __('Collation') }}</label>
                    <select class="nr-input" id="collation" name="collation">
                        @foreach (['utf8mb4_unicode_ci', 'utf8mb4_general_ci'] as $collation)
                            <option value="{{ $collation }}" @selected(old('collation', $data['collation'] ?? 'utf8mb4_unicode_ci') === $collation)>{{ $collation }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6" x-show="driver === 'sqlite'" x-cloak>
                <label class="nr-label" for="sqlite_path">{{ __('SQLite database file') }}</label>
                <input class="nr-input font-mono text-xs" id="sqlite_path" name="sqlite_path" value="{{ old('sqlite_path', $data['sqlite_path'] ?? database_path('database.sqlite')) }}">
                <p class="nr-help">{{ __('Use an absolute path outside the public web root. SQLite has limited write concurrency and is not recommended for busy servers.') }}</p>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button type="button" class="nr-btn-secondary" :disabled="testing"
                        @click="
                            testing = true; ok = null; errors = []; warnings = [];
                            fetch('{{ route('install.database.test') }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
                                body: new FormData(document.getElementById('database-form')),
                            }).then(async (response) => {
                                const data = await response.json().catch(() => ({ ok: false, errors: ['{{ __('Unexpected server response.') }}'] }));
                                ok = Boolean(data.ok);
                                errors = data.errors || [];
                                warnings = data.warnings || [];
                            }).catch((error) => { ok = false; errors = [error.message]; })
                            .finally(() => { testing = false; });
                        ">
                    <span x-show="!testing">{{ __('Test connection') }}</span>
                    <span x-show="testing" x-cloak>{{ __('Testing…') }}</span>
                </button>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Connection tests do not create application data.') }}</span>
            </div>

            <div class="mt-4 space-y-2" x-show="ok !== null" x-cloak>
                <div class="nr-success" x-show="ok">{{ __('The database connection is working and the account has the required privileges.') }}</div>
                <div class="nr-error" x-show="ok === false">
                    <template x-for="error in errors" :key="error"><p x-text="error"></p></template>
                </div>
                <div class="nr-warning" x-show="warnings.length > 0">
                    <template x-for="warning in warnings" :key="warning"><p x-text="warning"></p></template>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('install.requirements') }}" class="nr-btn-ghost">{{ __('Back') }}</a>
                <button type="submit" class="nr-btn-primary">{{ __('Save and continue') }}</button>
            </div>
        </form>
    </div>
@endsection
