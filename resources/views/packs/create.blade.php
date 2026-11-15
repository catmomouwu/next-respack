@extends('layouts.app')

@section('title', __('Upload resource pack'))
@section('content')
    @php
        $maxMb = min((int) nr_setting_int('max_upload_mb', (int) config('nextrespack.max_upload_mb', 512)), (int) $usage['remaining'] / 1024 / 1024);
        $phpMaxMb = min(
            (int) ini_get('upload_max_filesize') > 0 ? (int) ini_get('upload_max_filesize') : 999999,
            (int) ini_get('post_max_size') > 0 ? (int) ini_get('post_max_size') : 999999
        );
    @endphp

    <div class="mx-auto max-w-3xl">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Upload resource pack') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Only Minecraft Java Edition resource pack ZIP files are accepted.') }}</p>
            </div>
            <a href="{{ route('packs.index') }}" class="nr-btn-ghost">{{ __('Back') }}</a>
        </div>

        <div class="nr-card mt-6 p-6">
            <div class="mb-4 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>{{ __('Storage available') }}: {{ nr_bytes((int) $usage['remaining']) }}</span>
                <span>{{ __('Upload limit') }}: {{ round($maxMb, 1) }} MB</span>
            </div>

            <form method="POST" action="{{ route('packs.store') }}" enctype="multipart/form-data"
                  x-data="uploadForm" @submit.prevent="submit($event)">
                @csrf

                <div>
                    <label class="nr-label" for="file">{{ __('Resource pack ZIP') }}</label>
                    <input class="nr-input !py-3" id="file" name="file" type="file" accept=".zip,application/zip,application/x-zip-compressed" required>
                    <p class="nr-help">
                        {{ __('Maximum :size MB. Your PHP configuration allows up to :php MB. The archive is validated before anything is stored.', ['size' => round($maxMb, 1), 'php' => round($phpMaxMb, 1)]) }}
                    </p>
                </div>

                <div class="mt-4">
                    <label class="nr-label" for="name">{{ __('Display name') }} <span class="font-normal text-slate-400">({{ __('optional') }})</span></label>
                    <input class="nr-input" id="name" name="name" value="{{ old('name') }}" maxlength="180" placeholder="{{ __('My resource pack') }}">
                    <p class="nr-help">{{ __('Defaults to your original filename without the .zip extension.') }}</p>
                </div>

                <div class="mt-4">
                    <label class="nr-label" for="description">{{ __('Description') }} <span class="font-normal text-slate-400">({{ __('optional') }})</span></label>
                    <textarea class="nr-input" id="description" name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
                </div>

                <label class="mt-4 flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="allow_duplicate" value="1" class="mt-0.5 rounded text-emerald-600" @checked(old('allow_duplicate'))>
                    <span>{{ __('Allow uploading an identical file (same SHA-1) again. Leave unchecked to prevent accidental duplicates.') }}</span>
                </label>

                <div x-show="uploading" x-cloak class="mt-5 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <span class="max-w-[70%] truncate" x-text="filename"></span>
                        <span x-text="progress + '%'"></span>
                    </div>
                    <div class="nr-progress mt-2"><div class="nr-progress-bar" :style="'width: ' + progress + '%'"></div></div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Uploading and validating. Large files may take a while.') }}</p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('packs.index') }}" class="nr-btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="nr-btn-primary !px-6 !py-3" :disabled="uploading">{{ __('Upload and validate') }}</button>
                </div>
            </form>
        </div>

        <div class="nr-card-muted mt-4 p-5">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('What happens after upload?') }}</p>
            <ol class="mt-2 list-inside list-decimal space-y-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                <li>{{ __('The ZIP signature, integrity, CRC and security constraints are verified.') }}</li>
                <li>{{ __('The root pack.mcmeta is parsed and its pack format is matched against the Minecraft Wiki dataset.') }}</li>
                <li>{{ __('The file is stored under a UUIDv7 name in private storage and its SHA-1 is computed from the final bytes.') }}</li>
                <li>{{ __('An anonymous download token is generated. The database stores only its digest.') }}</li>
            </ol>
        </div>
    </div>
@endsection
