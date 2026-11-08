@extends('layouts.app')

@section('title', __('Minecraft Java Edition pack format list'))
@section('description', __('Search the complete Minecraft Java Edition resource pack format table. Find the pack format for any release, snapshot or pre-release version and copy it into pack.mcmeta.'))
@section('canonical', route('pack-formats.index'))
@section('content')
    <div>
        <span class="nr-badge-green">{{ __('Minecraft Java Edition · Resource Pack Format') }}</span>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Pack format database') }}</h1>
        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ __('Search resource pack formats and their Minecraft Java Edition versions. Data is collected from the Minecraft Wiki pack format page and includes releases, snapshots, pre-releases and release candidates.') }}
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span>{{ __(':count mapped versions', ['count' => number_format($totalVersions)]) }}</span>
            <span>·</span>
            <span>{{ __('Updated') }}: {{ $lastSync?->created_at?->format('Y-m-d H:i') ?? __('bundled data') }}</span>
            <span>·</span>
            <a href="https://minecraft.wiki/w/Pack_format" target="_blank" rel="noopener" class="nr-link">{{ __('Data source') }}</a>
            <span>·</span>
            <a href="{{ route('pack-formats.api') }}" class="nr-link">JSON API</a>
        </div>
    </div>

    <form method="GET" action="{{ route('pack-formats.index') }}" class="nr-card mt-6 p-4">
        <div class="grid gap-3 sm:grid-cols-[1.5fr_1fr_auto]">
            <input class="nr-input" type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search version (e.g. 1.21.9) or format (e.g. 69)…') }}">
            <select class="nr-input" name="type">
                <option value="">{{ __('All version types') }}</option>
                @foreach ($types as $value)
                    <option value="{{ $value }}" @selected($type === $value)>
                        {{ ['release' => __('Release'), 'snapshot' => __('Snapshot'), 'prerelease' => __('Pre-release'), 'release_candidate' => __('Release candidate'), 'experimental' => __('Experimental')][$value] ?? $value }}
                    </option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button class="nr-btn-primary" type="submit">{{ __('Search') }}</button>
                @if ($q !== '' || $type !== '' || $format !== '')
                    <a href="{{ route('pack-formats.index') }}" class="nr-btn-ghost">{{ __('Clear') }}</a>
                @endif
            </div>
        </div>
        @if ($format !== '')
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Filtering by format :format', ['format' => $format]) }}</p>
        @endif
    </form>

    @if ($formats->isEmpty())
        <div class="nr-card mt-4 p-10 text-center text-sm text-slate-500 dark:text-slate-400">
            {{ __('No matching format records. An administrator can synchronize the dataset from the admin panel.') }}
        </div>
    @else
        <div class="nr-card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="nr-table">
                    <thead>
                        <tr>
                            <th class="nr-th">{{ __('Resource pack format') }}</th>
                            <th class="nr-th">{{ __('Minecraft Java Edition versions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($formats as $formatRow)
                            <tr>
                                <td class="nr-td align-top">
                                    <a href="{{ route('pack-formats.show', $formatRow->format_value) }}" class="font-mono text-lg font-bold text-slate-900 underline-offset-4 hover:text-emerald-600 hover:underline dark:text-slate-100 dark:hover:text-emerald-400">{{ $formatRow->format_value }}</a>
                                    <p class="mt-1 text-[11px] text-slate-400">{{ __('Major :major · Minor :minor', ['major' => (string) $formatRow->format_major, 'minor' => $formatRow->format_minor === null ? '—' : (string) $formatRow->format_minor]) }}</p>
                                </td>
                                <td class="nr-td">
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse ($formatRow->versions as $versionRow)
                                            <span class="nr-badge {{ match ($versionRow->version_type) {
                                                'release' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                                'snapshot' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                                'prerelease' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                                'release_candidate' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
                                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                            } }}">
                                                {{ $versionRow->version_name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-400">—</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 pb-4">{{ $formats->links() }}</div>
        </div>
    @endif

    <div class="nr-warning mt-6">
        {{ __('Format compatibility is necessary but not sufficient. Textures, models, sounds and other resources in a pack may still be incompatible with a given Minecraft version.') }}
    </div>
@endsection
