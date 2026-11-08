@extends('layouts.app')

@section('title', $pack->name)
@section('content')
    @php
        $entry = $versions->first();
        $version = $entry['model'] ?? null;
        $downloadUrl = $entry['url'] ?? null;
    @endphp

    <div class="mx-auto max-w-3xl">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <p class="flex items-center gap-2 font-semibold text-emerald-800 dark:text-emerald-200">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-xs text-white dark:bg-emerald-500 dark:text-slate-950">✓</span>
                {{ __('Guest upload completed') }}
            </p>
            <p class="mt-1.5 text-sm text-emerald-700 dark:text-emerald-300">
                {{ __('Your download link is ready. This guest upload is deleted automatically after :days days.', ['days' => $retentionDays]) }}
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
                <div class="mt-3 flex flex-col gap-2 sm:flex-row" x-data="{ copied: false }">
                    <input x-ref="link" class="nr-input font-mono text-xs" type="text" value="{{ $downloadUrl }}" readonly onclick="this.select()">
                    <button type="button" class="nr-btn-secondary shrink-0"
                            @click="
                                if (navigator.clipboard) { navigator.clipboard.writeText($refs.link.value) } else { $refs.link.select(); document.execCommand('copy') }
                                copied = true; setTimeout(() => copied = false, 1500)
                            ">
                        <span x-show="!copied">{{ __('Copy link') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </button>
                </div>

                @if ($version?->sha1)
                    <div class="mt-3">
                        <p class="text-xs text-slate-500 dark:text-slate-400">SHA-1</p>
                        <p class="mt-0.5 break-all font-mono text-xs text-slate-600 dark:text-slate-300">{{ $version->sha1 }}</p>
                    </div>
                @endif
            @endif
        </div>

        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">{{ __('This upload is temporary') }}</p>
            <p class="mt-1 leading-relaxed">
                {{ __('The file and download link will be deleted on :date. Register to keep your resource packs permanently.', ['date' => $pack->guest_expires_at?->format('Y-m-d H:i')]) }}
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
