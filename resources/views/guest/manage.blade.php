@extends('layouts.app')

@section('title', __('Manage guest upload'))
@section('content')
    @php
        $remaining = max(0, (int) $remainingSeconds);
        $remainingDays = intdiv($remaining, 86400);
        $remainingHours = intdiv($remaining % 86400, 3600);
    @endphp

    <div class="mx-auto max-w-3xl">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $pack->name }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Manage this guest upload with your private management link.') }}</p>
            </div>
            <span class="nr-badge-amber">{{ __('Guest') }}</span>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Filename') }}</p>
                <p class="mt-1 break-all text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $version?->original_filename ?? '—' }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('File size') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ nr_bytes((int) ($version?->file_size ?? 0)) }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">SHA-1</p>
                <p class="mt-1 break-all font-mono text-xs text-slate-700 dark:text-slate-300">{{ $version?->sha1 ?? '—' }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Uploaded') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $pack->created_at?->format('Y-m-d H:i') }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Expires') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $pack->guest_expires_at?->format('Y-m-d H:i') }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Remaining time') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __(':days days :hours hours', ['days' => $remainingDays, 'hours' => $remainingHours]) }}</p>
            </div>
        </div>

        <div class="nr-card mt-5 p-5">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Download link') }}</p>
            @if ($downloadUrl)
                <div class="mt-3 flex flex-col gap-2 sm:flex-row" x-data="copyText">
                    <input x-ref="download" class="nr-input font-mono text-xs" type="text" value="{{ $downloadUrl }}" readonly onclick="this.select()">
                    <button type="button" class="nr-btn-secondary shrink-0" @click="copy($refs.download.value)">
                        <span x-show="!copied">{{ __('Copy link') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </button>
                </div>
            @else
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('This guest upload has no downloadable version.') }}</p>
            @endif

            @if ($suggestedVersion)
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Suggested version: :version', ['version' => $suggestedVersion['version_name']]) }}</p>
            @else
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Unable to determine a suggested version.') }}</p>
            @endif
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Rename') }}</p>
            <form method="POST" action="{{ route('guest.manage.update', ['token' => $token]) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                @csrf
                @method('PATCH')
                <input class="nr-input" id="name" name="name" value="{{ old('name', $pack->name) }}" required maxlength="180">
                <button class="nr-btn-primary shrink-0" type="submit">{{ __('Save name') }}</button>
            </form>
        </div>

        <div class="nr-card mt-4 p-5">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Delete early') }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('This deletes the stored file and invalidates the download link immediately. This cannot be undone.') }}</p>
            <form method="POST" action="{{ route('guest.manage.destroy', ['token' => $token]) }}" class="mt-3" onsubmit="return confirm('{{ __('Delete this guest upload now?') }}')">
                @csrf
                @method('DELETE')
                <button class="nr-btn-danger" type="submit">{{ __('Delete now') }}</button>
            </form>
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
            {{ __('This guest upload expires on :date and is deleted automatically.', ['date' => $pack->guest_expires_at?->format('Y-m-d H:i')]) }}
        </div>
    </div>
@endsection
