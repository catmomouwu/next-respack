@extends('layouts.app')

@section('title', __('Minecraft server resource pack hosting & resource-pack link generator'))
@section('description', __('Host resource packs for your Minecraft server, then copy the link into server.properties. ZIP files are validated automatically and served over HTTPS with a SHA-1 checksum.'))
@section('canonical', route('home'))

@push('head')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
            [
                '@type' => 'Question',
                'name' => __('How do I set a resource pack on my Minecraft server?'),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => __('Upload your pack, copy the resource-pack URL and SHA-1, then add them to server.properties and restart the server.'),
                ],
            ],
            [
                '@type' => 'Question',
                'name' => __('Do players need to install anything?'),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => __('No. The client downloads the pack automatically from the URL you configure.'),
                ],
            ],
            [
                '@type' => 'Question',
                'name' => __('Can I use this for resource packs bundled with a map?'),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => __('Yes. Use the same direct link in your map or server configuration.'),
                ],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush
@section('content')
    <section class="grid items-center gap-10 py-6 lg:grid-cols-[1.25fr_1fr] lg:py-12">
        <div>
            <span class="nr-badge-green">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ __('Built for Minecraft server owners') }}
            </span>
            <h1 class="mt-4 text-4xl font-extrabold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl dark:text-slate-100">
                {{ __('Host resource packs for your Minecraft server,') }}<br>
                <span class="text-emerald-600 dark:text-emerald-400">{{ __('copy the link and start playing.') }}</span>
            </h1>
            <p class="mt-5 max-w-2xl text-base leading-relaxed text-slate-600 dark:text-slate-300">
                {{ __('Upload a resource pack ZIP once. Next Respack validates pack.mcmeta and CRC, detects the pack format, and gives you an HTTPS direct link plus SHA-1 for server.properties, maps or modpacks.') }}
            </p>

            <div class="mt-7 flex flex-wrap items-center gap-3">
                @auth
                    <a href="{{ route('packs.create') }}" class="nr-btn-primary !px-6 !py-3">{{ __('Upload a resource pack') }}</a>
                    <a href="{{ route('dashboard') }}" class="nr-btn-secondary !px-6 !py-3">{{ __('Open dashboard') }}</a>
                @else
                    <a href="{{ route('guest.upload') }}" class="nr-btn-primary !px-6 !py-3">{{ __('Guest upload') }}</a>
                    @if (nr_setting_bool('allow_registration', true))
                        <a href="{{ route('register') }}" class="nr-btn-secondary !px-6 !py-3">{{ __('Create account') }}</a>
                    @endif
                    <a href="{{ route('login') }}" class="nr-btn-ghost">{{ __('Sign in') }}</a>
                @endauth
                <a href="#server-setup" class="nr-btn-ghost">{{ __('Server setup') }} →</a>
            </div>

            @guest
                <p class="mt-3 max-w-xl text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ __('Guest uploads are limited to :size MB and deleted after :days days. Register to keep your packs permanently.', ['size' => (int) config('nextrespack.guest_upload_mb', 200), 'days' => (int) config('nextrespack.guest_retention_days', 7)]) }}
                </p>
            @endguest

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

    <section id="server-owners" class="mt-12">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="nr-section-title">{{ __('How server owners use Next Respack') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Three steps from a ZIP file to a working resource pack on your server.') }}</p>
            </div>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            @foreach ([
                ['step' => '01', 'title' => __('Upload the ZIP'), 'body' => __('Validate pack.mcmeta, CRC and archive safety automatically.')],
                ['step' => '02', 'title' => __('Copy the direct link'), 'body' => __('Get an HTTPS download URL and SHA-1 for each version.')],
                ['step' => '03', 'title' => __('Paste into server.properties'), 'body' => __('Set resource-pack, resource-pack-sha1 and require-resource-pack.')],
            ] as $step)
                <div class="nr-card p-5">
                    <span class="font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ $step['step'] }}</span>
                    <p class="mt-2 font-semibold text-slate-900 dark:text-slate-100">{{ $step['title'] }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section id="server-setup" class="mt-12 grid gap-4 lg:grid-cols-[1.1fr_1fr]">
        <div class="nr-card p-6">
            <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Minecraft server resource pack setup') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ __('Paste this into server.properties, restart the server, and players will download the pack automatically. The same link works for map makers who bundle a resource pack with their world.') }}</p>
            <ol class="mt-4 list-inside list-decimal space-y-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                <li>{{ __('Upload your pack and copy the resource-pack URL and SHA-1.') }}</li>
                <li>{{ __('Open server.properties and set the three lines shown here.') }}</li>
                <li>{{ __('Restart the server; players download the pack on join.') }}</li>
            </ol>
        </div>
        <div class="nr-card overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-900/60">
                <span class="font-mono text-xs text-slate-500 dark:text-slate-400">server.properties</span>
            </div>
            <pre class="overflow-x-auto p-5 font-mono text-xs leading-6 text-slate-600 dark:text-slate-300"><code>resource-pack=https://mcpack.moyv.dpdns.org/dl/&lt;token&gt;
resource-pack-sha1=4f2a9c8e6b1d3f5a7c9e0b2d4f6a8c1e3b5d7f9a
require-resource-pack=true</code></pre>
        </div>
    </section>

    <section class="mt-12">
        <h2 class="nr-section-title">{{ __('Why server owners choose Next Respack') }}</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ([
                ['title' => __('Stable HTTPS direct links'), 'body' => __('No more expired cloud drive links or 403 errors. Every download uses a stable HTTPS URL.')],
                ['title' => __('Validated before players join'), 'body' => __('Validation catches broken resource packs before players see errors.')],
                ['title' => __('Free guest upload, permanent accounts'), 'body' => __('Guest upload is free; registered accounts keep every version permanently.')],
                ['title' => __('Open source and self-hostable'), 'body' => __('Open source and self-hostable, so you can run it on your own domain.')],
            ] as $reason)
                <div class="nr-card p-5">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $reason['title'] }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $reason['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section id="faq" class="mt-12">
        <h2 class="nr-section-title">{{ __('Frequently asked questions') }}</h2>
        <div class="mt-4 space-y-3">
            @foreach ([
                ['q' => __('How do I set a resource pack on my Minecraft server?'), 'a' => __('Upload your pack, copy the resource-pack URL and SHA-1, then add them to server.properties and restart the server.')],
                ['q' => __('Do players need to install anything?'), 'a' => __('No. The client downloads the pack automatically from the URL you configure.')],
                ['q' => __('Can I use this for resource packs bundled with a map?'), 'a' => __('Yes. Use the same direct link in your map or server configuration.')],
            ] as $faq)
                <details class="nr-card p-5">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $faq['q'] }}</summary>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $faq['a'] }}</p>
                </details>
            @endforeach
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
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $pack->user?->displayName() ?? __('Guest') }} · {{ $pack->created_at?->diffForHumans() }}</p>
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
