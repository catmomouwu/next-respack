@extends('layouts.app')

@section('title', __('Minecraft resource pack format :format', ['format' => $format->format_value]))
@section('description', __('Minecraft Java Edition resource pack format :format is used by :count mapped versions. See the complete version list, release dates and a pack.mcmeta example.', ['format' => $format->format_value, 'count' => $versions->count()]))
@section('canonical', route('pack-formats.show', $format->format_value))

@push('head')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('Minecraft pack formats'), 'item' => route('pack-formats.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => __('Resource pack format :format', ['format' => $format->format_value]), 'item' => route('pack-formats.show', $format->format_value)],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <div class="mx-auto max-w-4xl">
        <nav aria-label="Breadcrumb" class="text-xs text-slate-500 dark:text-slate-400">
            <a href="{{ route('home') }}" class="nr-link">{{ __('Home') }}</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('pack-formats.index') }}" class="nr-link">{{ __('Minecraft pack formats') }}</a>
            <span class="mx-1.5">/</span>
            <span class="font-mono">{{ $format->format_value }}</span>
        </nav>

        <div class="mt-4">
            <span class="nr-badge-green">{{ __('Minecraft Java Edition · Resource Pack Format') }}</span>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                {{ __('Resource pack format :format', ['format' => $format->format_value]) }}
            </h1>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                {{ __('Resource pack format :format belongs to format major :major and minor :minor. The table below lists every mapped Minecraft Java Edition version and its release date.', [
                    'format' => $format->format_value,
                    'major' => (string) $format->format_major,
                    'minor' => $format->format_minor === null ? '—' : (string) $format->format_minor,
                ]) }}
            </p>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Resource pack format') }}</p>
                <p class="mt-1 font-mono text-xl font-bold text-slate-900 dark:text-slate-100">{{ $format->format_value }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Mapped versions') }}</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ number_format($versions->count()) }}</p>
            </div>
            <div class="nr-card-muted px-4 py-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Release versions') }}</p>
                <p class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ number_format($releaseCount) }}</p>
            </div>
        </div>

        <div class="nr-card mt-4 p-5">
            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('pack.mcmeta example') }}</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Copy this value into pack.mcmeta to declare compatibility with the versions listed below.') }}</p>
            <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-950 px-4 py-3 text-xs leading-relaxed text-slate-100"><code>{
  "pack": {
    "pack_format": {{ $format->format_value }},
    "description": "My resource pack"
  }
}</code></pre>
        </div>

        <div class="mt-6 flex flex-wrap items-end justify-between gap-2">
            <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ __('Version list') }}</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Updated') }}: {{ $format->updated_at?->format('Y-m-d H:i') }}</span>
        </div>

        <div class="nr-card mt-3 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="nr-table">
                    <thead>
                        <tr>
                            <th class="nr-th">{{ __('Version') }}</th>
                            <th class="nr-th">{{ __('Type') }}</th>
                            <th class="nr-th">{{ __('Release date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($versions as $versionRow)
                            <tr>
                                <td class="nr-td font-mono text-sm text-slate-900 dark:text-slate-100">{{ $versionRow->version_name }}</td>
                                <td class="nr-td">
                                    <span class="nr-badge {{ match ($versionRow->version_type) {
                                        'release' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                        'snapshot' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                        'prerelease' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                        'release_candidate' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
                                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                    } }}">
                                        {{ ['release' => __('Release'), 'snapshot' => __('Snapshot'), 'prerelease' => __('Pre-release'), 'release_candidate' => __('Release candidate'), 'experimental' => __('Experimental')][$versionRow->version_type] ?? $versionRow->version_type }}
                                    </span>
                                </td>
                                <td class="nr-td text-sm text-slate-500 dark:text-slate-400">{{ $versionRow->release_date?->format('Y-m-d') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="nr-td text-sm text-slate-400" colspan="3">{{ __('No version records for this format yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm">
            <div class="flex flex-wrap gap-3">
                @if ($previous)
                    <a href="{{ route('pack-formats.show', $previous->format_value) }}" class="nr-btn-secondary">← {{ __('Format :format', ['format' => $previous->format_value]) }}</a>
                @endif
                @if ($next)
                    <a href="{{ route('pack-formats.show', $next->format_value) }}" class="nr-btn-secondary">{{ __('Format :format', ['format' => $next->format_value]) }} →</a>
                @endif
            </div>
            <a href="{{ route('pack-formats.index') }}" class="nr-link">{{ __('View all pack formats') }}</a>
        </div>

        <div class="nr-warning mt-6">
            {{ __('Format compatibility is necessary but not sufficient. Textures, models, sounds and other resources in a pack may still be incompatible with a given Minecraft version.') }}
        </div>
    </div>
@endsection
