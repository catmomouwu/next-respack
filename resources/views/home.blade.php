@extends('layouts.app')

@section('title', __('Home'))
@section('content')
    <section class="grid items-center gap-10 py-6 lg:grid-cols-[1.25fr_1fr] lg:py-12">
        <div>
            <span class="nr-badge-green">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ __('Self-hosted · Minecraft Java Edition') }}
            </span>
            <h1 class="mt-4 text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl dark:text-slate-100">
                {{ __('Your resource packs,') }}<br>
                <span class="text-emerald-600 dark:text-emerald-400">{{ __('properly hosted and validated.') }}</span>
            </h1>
            <p class="mt-5 max-w-2xl text-base leading-relaxed text-slate-600 dark:text-slate-300">
                {{ __('Upload Minecraft Java Edition resource pack ZIP files, validate their structure, compute SHA-1, detect pack formats and generate anonymous HTTPS download links with server.properties snippets.') }}
            </p>

            <div class="mt-7 flex flex-wrap items-center gap-3">
                @auth
                    <a href="{{ route('packs.create') }}" class="nr-btn-primary !px-6 !py-3">{{ __('Upload a resource pack') }}</a>
                    <a href="{{ route('dashboard') }}" class="nr-btn-secondary !px-6 !py-3">{{ __('Open dashboard') }}</a>
                @else
                    @if (nr_setting_bool('allow_registration', true))
                        <a href="{{ route('register') }}" class="nr-btn-primary !px-6 !py-3">{{ __('Get started') }}</a>
                    @endif
                    <a href="{{ route('login') }}" class="nr-btn-secondary !px-6 !py-3">{{ __('Sign in') }}</a>
                @endauth
                <a href="{{ route('pack-formats.index') }}" class="nr-btn-ghost">{{ __('Browse pack formats') }} →</a>
            </div>

            <div class="mt-9 grid max-w-xl grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([
                    ['value' => $stats['packs'], 'label' => __('Active packs')],
                    ['value' => $stats['downloads'], 'label' => __('Downloads')],
                    ['value' => $stats['formats'], 'label' => __('Formats')],
                    ['value' => $stats['versions'], 'label' => __('Mapped versions')],
                ] as $stat)
                    <div class="nr-card-muted px-4 py-3">
                        <p class="text-xl font-bold text-slate-900 dark:text-slate-100">{{ number_format($stat['value']) }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="relative">
            <div class="nr-card overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-900/60">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                        <span class="ml-2 font-mono text-xs text-slate-500 dark:text-slate-400">server.properties</span>
                    </div>
                </div>
                <pre class="overflow-x-auto p-5 font-mono text-xs leading-6 text-slate-600 dark:text-slate-300"><code>resource-pack=https://your-domain.example/dl/&lt;token&gt;
resource-pack-sha1=4f2a9c8e6b1d3f5a7c9e0b2d4f6a8c1e3b5d7f9a
require-resource-pack=true</code></pre>
            </div>
            <div class="nr-card mt-4 p-5">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Validation pipeline') }}</p>
                    <span class="nr-badge-green">{{ __('6 checks') }}</span>
                </div>
                <ul class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-300">
                    @foreach ([
                        __('ZIP signature, CRC and integrity'),
                        __('Zip Slip, symlink and ZIP bomb protection'),
                        __('Root pack.mcmeta and JSON schema'),
                        __('Pack format detection against the wiki dataset'),
                        __('SHA-1 of the final stored file'),
                        __('Anonymous, revocable download token'),
                    ] as $item)
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-600 dark:text-emerald-400">✓</span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="mt-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="nr-section-title">{{ __('Latest pack format mappings') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Sourced from the Minecraft Wiki pack format history.') }}</p>
            </div>
            <a href="{{ route('pack-formats.index') }}" class="nr-link text-sm">{{ __('View full database') }} →</a>
        </div>

        @if ($latestFormats->isEmpty())
            <div class="nr-card mt-4 p-8 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('Pack format data has not been imported yet. An administrator can synchronize it from the admin panel.') }}
            </div>
        @else
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($latestFormats as $format)
                    <a href="{{ route('pack-formats.index', ['format' => $format->format_value]) }}" class="nr-card group p-4 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:hover:border-emerald-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Format') }}</p>
                        <p class="mt-1 font-mono text-2xl font-bold text-slate-900 group-hover:text-emerald-600 dark:text-slate-100 dark:group-hover:text-emerald-400">{{ $format->format_value }}</p>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ $format->versions->take(3)->pluck('version_name')->implode(', ') }}
                            @if ($format->versions->count() > 3)
                                +{{ $format->versions->count() - 3 }}
                            @endif
                        </p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="mt-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="nr-section-title">{{ __('Recently published resource packs') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Pack names are listed for discovery. Downloads are only available through the anonymous link shared by the owner.') }}</p>
            </div>
        </div>

        @if ($latestPacks->isEmpty())
            <div class="nr-card mt-4 p-8 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('No resource packs have been published yet.') }}
            </div>
        @else
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latestPacks as $pack)
                    <div class="nr-card p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ $pack->name }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $pack->user?->displayName() }} · {{ $pack->created_at?->diffForHumans() }}</p>
                            </div>
                            <span class="nr-badge-slate shrink-0">{{ __('Format') }} {{ $pack->displayFormat() }}</span>
                        </div>
                        @if ($pack->description)
                            <p class="mt-3 line-clamp-2 text-sm text-slate-600 dark:text-slate-300">{{ $pack->description }}</p>
                        @endif
                        <p class="mt-3 font-mono text-xs text-slate-400">{{ nr_bytes((int) ($pack->currentVersion?->file_size ?? 0)) }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="mt-12">
        <div class="nr-card flex flex-col items-start justify-between gap-4 p-6 sm:flex-row sm:items-center">
            <div>
                <p class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                    <span class="nr-badge-green">{{ __('Open source') }}</span>
                    {{ __('This is an open source project') }}
                </p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Next Respack is released under the MIT license. Star, fork or contribute on GitHub.') }}
                </p>
            </div>
            <a href="https://github.com/catmomouwu/next-respack" target="_blank" rel="noopener noreferrer" class="nr-btn-secondary shrink-0 font-mono text-xs">
                github.com/catmomouwu/next-respack ↗
            </a>
        </div>
    </section>
@endsection
