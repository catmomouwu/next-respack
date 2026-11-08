@extends('layouts.app')

@section('title', __('Upload a Minecraft resource pack without an account'))
@section('description', __('Upload a Minecraft Java Edition resource pack ZIP without registering. Files up to :size MB are validated instantly and deleted after :days days.', ['size' => (int) config('nextrespack.guest_upload_mb', 200), 'days' => (int) config('nextrespack.guest_retention_days', 7)]))
@section('canonical', route('guest.upload'))
@section('content')
    @php
        $phpMaxMb = min(
            (int) ini_get('upload_max_filesize') > 0 ? (int) ini_get('upload_max_filesize') : 999999,
            (int) ini_get('post_max_size') > 0 ? (int) ini_get('post_max_size') : 999999
        );
        $effectiveMax = min($maxMb, $phpMaxMb);
    @endphp

    <div class="mx-auto max-w-3xl">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Guest upload') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Upload a resource pack without registering an account.') }}</p>
            </div>
            <a href="{{ route('home') }}" class="nr-btn-ghost">{{ __('Back') }}</a>
        </div>

        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">{{ __('Guest uploads are temporary') }}</p>
            <p class="mt-1 leading-relaxed">
                {{ __('Each guest upload is limited to :size MB and is deleted automatically after :days days.', ['size' => $maxMb, 'days' => $retentionDays]) }}
                {{ __('Register to keep your resource packs permanently.') }}
                @if (nr_setting_bool('allow_registration', true))
                    <a href="{{ route('register') }}" class="font-semibold underline underline-offset-4 hover:text-amber-700 dark:hover:text-amber-100">{{ __('Register for permanent storage') }} →</a>
                @endif
            </p>
        </div>

        <div class="nr-card mt-4 p-6">
            <form method="POST" action="{{ route('guest.upload.store') }}" enctype="multipart/form-data" x-data="uploadForm" @submit.prevent="submit($event)">
                @csrf

                <div>
                    <label class="nr-label" for="file">{{ __('Resource pack ZIP') }}</label>
                    <input class="nr-input !py-3" id="file" name="file" type="file" accept=".zip,application/zip,application/x-zip-compressed" required>
                    <p class="nr-help">
                        {{ __('Only .zip files up to :size MB are accepted. The archive is validated before anything is stored.', ['size' => round($effectiveMax, 1)]) }}
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

                <div class="mt-5" x-data="{ src: '{{ route('captcha') }}?t={{ \Illuminate\Support\Str::uuid() }}' }">
                    <label class="nr-label" for="captcha">{{ __('Captcha') }}</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <img :src="src" src="{{ route('captcha') }}?t={{ \Illuminate\Support\Str::uuid() }}" width="180" height="60" alt="{{ __('Captcha image') }}" class="rounded-lg border border-slate-200 bg-white dark:border-slate-700">
                        <button type="button" class="nr-btn-ghost !px-3 !py-2 text-xs" @click="src = '{{ route('captcha') }}?t=' + Date.now()">
                            {{ __('Refresh captcha') }}
                        </button>
                        <input class="nr-input max-w-40 font-mono uppercase tracking-widest" id="captcha" name="captcha" value="" maxlength="5" required autocomplete="off" inputmode="text" placeholder="ABCDE">
                    </div>
                    <p class="nr-help">{{ __('Enter the characters shown in the image. The code expires after 10 minutes.') }}</p>
                </div>

                <div x-show="uploading" x-cloak class="mt-5 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <span class="max-w-[70%] truncate" x-text="filename"></span>
                        <span x-text="progress + '%'"></span>
                    </div>
                    <div class="nr-progress mt-2"><div class="nr-progress-bar" :style="'width: ' + progress + '%'"></div></div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Uploading and validating. Large files may take a while.') }}</p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('home') }}" class="nr-btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="nr-btn-primary !px-6 !py-3" :disabled="uploading">{{ __('Upload as guest') }}</button>
                </div>
            </form>
        </div>

        <div class="nr-card-muted mt-4 p-5">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('What happens after upload?') }}</p>
            <ol class="mt-2 list-inside list-decimal space-y-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                <li>{{ __('The ZIP signature, integrity, CRC and security constraints are verified.') }}</li>
                <li>{{ __('The root pack.mcmeta is parsed and its pack format is matched against the Minecraft Wiki dataset.') }}</li>
                <li>{{ __('The file is stored under a UUIDv7 name in private storage and its SHA-1 is computed from the final bytes.') }}</li>
                <li>{{ __('You receive an anonymous download link that stays valid until the guest upload expires.') }}</li>
            </ol>
        </div>
    </div>
@endsection
