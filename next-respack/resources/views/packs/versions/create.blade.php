@extends('layouts.app')

@section('title', __('Upload new version'))
@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Upload new version') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $pack->name }}</p>
            </div>
            <a href="{{ route('packs.show', $pack) }}" class="nr-btn-ghost">{{ __('Back') }}</a>
        </div>

        <div class="nr-card mt-6 p-6">
            <form method="POST" action="{{ route('packs.versions.store', $pack) }}" enctype="multipart/form-data"
                  x-data="uploadForm" @submit.prevent="submit($event)">
                @csrf
                <div>
                    <label class="nr-label" for="file">{{ __('Resource pack ZIP') }}</label>
                    <input class="nr-input !py-3" id="file" name="file" type="file" accept=".zip,application/zip,application/x-zip-compressed" required>
                    <p class="nr-help">{{ __('Maximum :size MB. Existing versions are never modified.', ['size' => nr_setting_int('max_upload_mb', (int) config('nextrespack.max_upload_mb', 512))]) }}</p>
                </div>
                <div class="mt-4">
                    <label class="nr-label" for="description">{{ __('Version notes') }} <span class="font-normal text-slate-400">({{ __('optional') }})</span></label>
                    <textarea class="nr-input" id="description" name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
                </div>
                <label class="mt-4 flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="allow_duplicate" value="1" class="mt-0.5 rounded text-emerald-600" @checked(old('allow_duplicate'))>
                    <span>{{ __('Allow an identical file (same SHA-1) as another version.') }}</span>
                </label>

                <div x-show="uploading" x-cloak class="mt-5 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                        <span class="max-w-[70%] truncate" x-text="filename"></span>
                        <span x-text="progress + '%'"></span>
                    </div>
                    <div class="nr-progress mt-2"><div class="nr-progress-bar" :style="'width: ' + progress + '%'"></div></div>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('packs.show', $pack) }}" class="nr-btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="nr-btn-primary" :disabled="uploading">{{ __('Upload version') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
