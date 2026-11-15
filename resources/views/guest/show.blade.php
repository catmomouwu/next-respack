@extends('layouts.app')

@section('title', $pack->name)
@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <p class="flex items-center gap-2 font-semibold text-emerald-800 dark:text-emerald-200">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-xs text-white dark:bg-emerald-500 dark:text-slate-950">✓</span>
                {{ __('Guest upload completed') }}
            </p>
            <p class="mt-1.5 text-sm text-emerald-700 dark:text-emerald-300">
                {{ __('Your download link and management link are ready. This guest upload is deleted automatically after :days days.', ['days' => $retentionDays]) }}
            </p>
        </div>

        <div class="mt-5 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $pack->name }}</h1>
                @if ($pack->description)
                    <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-300">{{ $pack->description }}</p>
                @endif
            </div>
            <span class="nr-badge-amber">{{ __('Guest') }}</span>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Pack format') }}</p>
                <p class="mt-1 font-mono text-lg font-bold text-slate-900 dark:text-slate-100">{{ $pack->displayFormat() }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('File size') }}</p>
                <p class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{{ nr_bytes((int) ($version?->file_size ?? 0)) }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Uploaded') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $pack->created_at?->format('Y-m-d H:i') }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Expires') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $pack->guest_expires_at?->format('Y-m-d H:i') }}</p>
            </div>
        </div>

        <div class="nr-card mt-5 p-5">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Your download link') }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Anyone with this link can download the pack until it expires.') }}</p>

            @if ($downloadUrl)
                <div class="mt-3 flex flex-col gap-2 sm:flex-row" x-data="copyText">
                    <input x-ref="download" class="nr-input font-mono text-xs" type="text" value="{{ $downloadUrl }}" readonly onclick="this.select()">
                    <button type="button" class="nr-btn-secondary shrink-0" @click="copy($refs.download.value)">
                        <span x-show="!copied">{{ __('Copy link') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </button>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">SHA-1</p>
                        <p class="mt-0.5 break-all font-mono text-xs text-slate-600 dark:text-slate-300">{{ $version->sha1 }}</p>
                    </div>
                    <div>
                        @include('components.recommended-version', ['version' => $version])
                    </div>
                </div>
            @endif
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Management link') }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Keep this link private. It is the only way to rename or delete this guest upload. It is never part of the download URL.') }}
            </p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row" x-data="copyText">
                <input x-ref="manage" class="nr-input font-mono text-xs" type="text" value="{{ $managementUrl }}" readonly onclick="this.select()">
                <button type="button" class="nr-btn-secondary shrink-0" @click="copy($refs.manage.value)">
                    <span x-show="!copied">{{ __('Copy management link') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </button>
            </div>
            <a href="{{ $managementUrl }}" class="nr-link mt-3 inline-block text-xs">{{ __('Open the management page') }} →</a>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('server.properties') }}</p>
            @if ($serverPropertiesWarning)
                <div class="nr-warning mt-3 text-xs">{{ $serverPropertiesWarning }}</div>
            @endif
            @if ($serverProperties)
                <div x-data="copyText">
                    <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-950 p-4 font-mono text-xs leading-6 text-slate-200">{{ $serverProperties }}</pre>
                    <div class="mt-3">
                        <button type="button" class="nr-btn-secondary !py-2 text-xs" @click="copy(@js($serverProperties))">
                            <span x-show="!copied">{{ __('Copy configuration') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">{{ __('This upload is temporary') }}</p>
            <p class="mt-1 leading-relaxed">
                {{ __('The file, download link and management link will be deleted on :date. Registered accounts keep their files permanently until they delete them.', ['date' => $pack->guest_expires_at?->format('Y-m-d H:i')]) }}
                @if (nr_setting_bool('allow_registration', true))
                    <a href="{{ route('register') }}" class="font-semibold underline underline-offset-4 hover:text-amber-700 dark:hover:text-amber-100">{{ __('Register for permanent storage') }} →</a>
                @endif
            </p>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <a href="{{ route('guest.upload') }}" class="nr-btn-primary">{{ __('Upload another pack') }}</a>
            <a href="{{ route('home') }}" class="nr-btn-ghost">{{ __('Back to home') }}</a>
        </div>
    </div>
@endsection
