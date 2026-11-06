@extends('layouts.admin')

@section('title', __('Correct pack format record'))
@section('heading', __('Correct pack format record'))
@section('content')
    <a href="{{ route('admin.pack-formats.index') }}" class="nr-btn-ghost">{{ __('← Back to pack formats') }}</a>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="nr-card p-5">
            <p class="nr-section-title">{{ $version->version_name }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Current format: :format · type: :type', ['format' => $version->format?->format_value ?? '—', 'type' => $version->version_type]) }}</p>

            <form method="POST" action="{{ route('admin.pack-formats.update', $version) }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="nr-label" for="version_name">{{ __('Minecraft version') }}</label>
                    <input class="nr-input" id="version_name" name="version_name" value="{{ old('version_name', $version->version_name) }}" required maxlength="191">
                </div>
                <div>
                    <label class="nr-label" for="format_value">{{ __('Resource pack format') }}</label>
                    <input class="nr-input font-mono" id="format_value" name="format_value" value="{{ old('format_value', $version->format?->format_value) }}" required placeholder="69 or 69.1">
                    <p class="nr-help">{{ __('Format values are preserved exactly. 69.1 is not converted to 691.') }}</p>
                </div>
                <div>
                    <label class="nr-label" for="version_type">{{ __('Version type') }}</label>
                    <select class="nr-input" id="version_type" name="version_type">
                        @foreach (['release' => __('Release'), 'snapshot' => __('Snapshot'), 'prerelease' => __('Pre-release'), 'release_candidate' => __('Release candidate'), 'experimental' => __('Experimental')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('version_type', $version->version_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="nr-label" for="release_date">{{ __('Release date') }}</label>
                    <input class="nr-input" id="release_date" name="release_date" type="date" value="{{ old('release_date', $version->release_date?->toDateString()) }}">
                </div>
                <p class="nr-help">{{ __('Saving marks this record as manual. It will be preserved unless the upstream count changes.') }}</p>
                <button class="nr-btn-primary" type="submit">{{ __('Save correction') }}</button>
            </form>

            @if ($version->is_manual)
                <form method="POST" action="{{ route('admin.pack-formats.destroy', $version) }}" class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800" onsubmit="return confirm('{{ __('Delete this manual record?') }}')">
                    @csrf
                    @method('DELETE')
                    <button class="nr-btn-danger" type="submit">{{ __('Delete manual record') }}</button>
                </form>
            @endif
        </div>

        <div class="nr-card p-5">
            <p class="nr-section-title">{{ __('Other manual records') }}</p>
            <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($versions as $record)
                    <a href="{{ route('admin.pack-formats.edit', $record) }}" class="flex items-center justify-between gap-3 py-2.5 text-xs hover:opacity-80">
                        <span class="font-mono text-slate-700 dark:text-slate-300">{{ $record->version_name }}</span>
                        <span class="nr-badge-amber">{{ $record->format?->format_value }}</span>
                    </a>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">{{ __('No manual records.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
