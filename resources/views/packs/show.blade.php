@extends('layouts.app')

@section('title', $pack->name)
@section('content')
    @php
        $maxDaily = max(1, collect($chart)->max('total'));
        $statusMap = [
            'VALID' => ['label' => __('Valid'), 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'],
            'VALID_WITH_WARNING' => ['label' => __('Valid with warnings'), 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300'],
            'FORMAT_UNKNOWN' => ['label' => __('Unknown format'), 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300'],
            'METADATA_INVALID' => ['label' => __('Invalid metadata'), 'class' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'],
            'PACK_INVALID' => ['label' => __('Invalid pack'), 'class' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'],
            'ZIP_INVALID' => ['label' => __('Invalid ZIP'), 'class' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'],
        ];
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('packs.index') }}" class="text-sm text-slate-500 hover:text-emerald-600 dark:text-slate-400">{{ __('My resource packs') }}</a>
                <span class="text-slate-300 dark:text-slate-600">/</span>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $pack->name }}</h1>
                <span class="nr-badge {{ $pack->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                    {{ $pack->status === 'active' ? __('Active') : __('Disabled') }}
                </span>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Created :date · :count versions · :size', ['date' => $pack->created_at?->format('Y-m-d'), 'count' => $versions->count(), 'size' => nr_bytes((int) $pack->totalSize())]) }}</p>
            @if ($pack->description)
                <p class="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">{{ $pack->description }}</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('packs.versions.create', $pack) }}" class="nr-btn-primary">{{ __('New version') }}</a>
            <form method="POST" action="{{ route('packs.toggle', $pack) }}">
                @csrf
                <button class="nr-btn-secondary" type="submit">{{ $pack->status === 'active' ? __('Disable all links') : __('Enable pack') }}</button>
            </form>
            <form method="POST" action="{{ route('packs.destroy', $pack) }}" onsubmit="return confirm('{{ __('Delete this resource pack and all versions? This cannot be undone.') }}')">
                @csrf
                @method('DELETE')
                <button class="nr-btn-danger" type="submit">{{ __('Delete') }}</button>
            </form>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="nr-card p-5 lg:col-span-2">
            <div class="flex items-center justify-between">
                <p class="nr-section-title !text-base">{{ __('Downloads · last 14 days') }}</p>
                <span class="nr-badge-slate">{{ number_format((int) $pack->totalDownloads()) }} {{ __('total') }}</span>
            </div>
            <div class="mt-5 flex h-32 items-end gap-1.5">
                @foreach ($chart as $bar)
                    <div class="group relative flex h-full flex-1 items-end" title="{{ $bar['day'] }}: {{ $bar['total'] }}">
                        <div class="w-full rounded-t bg-emerald-500/80 transition group-hover:bg-emerald-500 dark:bg-emerald-400/70" style="height: {{ max(3, round($bar['total'] / $maxDaily * 100)) }}%"></div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="nr-card p-5">
            <p class="nr-section-title !text-base">{{ __('Storage usage') }}</p>
            <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100">{{ nr_bytes((int) $usage['used']) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('of :limit total account quota', ['limit' => nr_bytes((int) $usage['limit'])]) }}</p>
            <div class="nr-progress mt-3"><div class="nr-progress-bar" style="width: {{ $usage['percent'] }}%"></div></div>
        </div>
    </div>

    <div class="nr-card mt-6">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h2 class="nr-section-title !text-base">{{ __('Versions and download links') }}</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Each version has its own permanent token. The database stores only a SHA-256 digest of the token.') }}</p>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($versions as $entry)
                @php
                    $version = $entry['model'];
                    $meta = $statusMap[$version->validation_status] ?? ['label' => $version->validation_status, 'class' => 'bg-slate-100 text-slate-600'];
                    $details = $version->validation_details ?? [];
                @endphp
                <div class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ __('Version :number', ['number' => $version->version_number]) }}</p>
                                <span class="nr-badge {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                                @if ($version->id === $pack->current_version_id)
                                    <span class="nr-badge bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">{{ __('Current') }}</span>
                                @endif
                                @if (! $version->download_enabled)
                                    <span class="nr-badge-red">{{ __('Link disabled') }}</span>
                                @endif
                            </div>
                            <p class="mt-1 break-all font-mono text-xs text-slate-500 dark:text-slate-400">{{ $version->original_filename }} · {{ nr_bytes($version->file_size) }} · {{ $version->created_at?->format('Y-m-d H:i') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <form method="POST" action="{{ route('packs.versions.toggle', [$pack, $version]) }}">
                                @csrf
                                <button class="nr-btn-secondary !px-2.5 !py-1.5 text-xs" type="submit">{{ $version->download_enabled ? __('Disable link') : __('Enable link') }}</button>
                            </form>
                            <form method="POST" action="{{ route('packs.versions.reset-token', [$pack, $version]) }}" onsubmit="return confirm('{{ __('Reset this token? The previous link will stop working immediately.') }}')">
                                @csrf
                                <button class="nr-btn-secondary !px-2.5 !py-1.5 text-xs" type="submit">{{ __('Reset token') }}</button>
                            </form>
                            <form method="POST" action="{{ route('packs.versions.destroy', [$pack, $version]) }}" onsubmit="return confirm('{{ __('Delete this version permanently?') }}')">
                                @csrf
                                @method('DELETE')
                                <button class="nr-btn-danger !px-2.5 !py-1.5 text-xs" type="submit">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2 rounded-xl bg-slate-50 p-3 font-mono text-xs dark:bg-slate-900/60 sm:grid-cols-[1fr_auto] sm:items-center">
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('Download URL') }}</p>
                            <p class="mt-1 break-all text-slate-700 dark:text-slate-300">{{ $entry['url'] }}</p>
                            <p class="mt-1 text-[10px] uppercase tracking-wide text-slate-400">SHA-1</p>
                            <p class="break-all text-slate-700 dark:text-slate-300">{{ $version->sha1 }}</p>
                        </div>
                        <div class="flex gap-1.5 sm:flex-col" x-data="copyText">
                            <button type="button" class="nr-btn-secondary !px-2.5 !py-1.5 text-xs" @click="copy('{{ $entry['url'] }}')">
                                <span x-show="!copied">{{ __('Copy URL') }}</span>
                                <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                            </button>
                            <button type="button" class="nr-btn-secondary !px-2.5 !py-1.5 text-xs" @click="copy('{{ $version->sha1 }}')">{{ __('Copy SHA-1') }}</button>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-2 text-xs sm:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-800">
                            <span class="text-slate-400">{{ __('Pack format') }}</span>
                            <p class="font-mono font-semibold text-slate-700 dark:text-slate-300">{{ $version->formatLabel() }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-800 sm:col-span-2">
                            <span class="text-slate-400">{{ __('Detected versions') }}</span>
                            <p class="mt-0.5 text-slate-700 dark:text-slate-300">
                                {{ is_array($version->detected_versions) && count($version->detected_versions) ? implode(', ', array_map(fn ($item) => is_array($item) ? ($item['version_name'] ?? '') : $item, $version->detected_versions)) : __('No matching Minecraft version in the current dataset') }}
                            </p>
                        </div>
                    </div>

                    @if (! empty($details['errors']) || ! empty($details['warnings']))
                        <div class="mt-3 space-y-2">
                            @foreach (($details['errors'] ?? []) as $error)
                                <div class="nr-error !py-2 text-xs">{{ $error }}</div>
                            @endforeach
                            @foreach (($details['warnings'] ?? []) as $warning)
                                <div class="nr-warning !py-2 text-xs">{{ $warning }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($version->pack_mcmeta_raw))
                        <details class="mt-3">
                            <summary class="cursor-pointer text-xs font-medium text-slate-500 hover:text-emerald-600 dark:text-slate-400">{{ __('Raw pack.mcmeta') }}</summary>
                            <pre class="mt-2 max-h-56 overflow-auto rounded-lg bg-slate-950 p-3 font-mono text-[11px] leading-5 text-slate-200">{{ $version->pack_mcmeta_raw }}</pre>
                        </details>
                    @endif
                </div>
            @empty
                <div class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No versions uploaded yet.') }}</div>
            @endforelse
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <div class="nr-card p-5">
            <h2 class="nr-section-title !text-base">{{ __('server.properties') }}</h2>
            @if ($serverPropertiesWarning)
                <div class="nr-warning mt-3 text-xs">{{ $serverPropertiesWarning }}</div>
            @endif
            @if ($serverProperties)
                <div x-data="copyText">
                    <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-950 p-4 font-mono text-xs leading-6 text-slate-200">{{ $serverProperties }}</pre>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="nr-btn-secondary !py-2 text-xs" @click="copy(@js($serverProperties))">
                            <span x-show="!copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                        </button>
                        <a href="{{ route('packs.server-properties', $pack) }}" class="nr-btn-secondary !py-2 text-xs">{{ __('Download .properties') }}</a>
                        <a href="{{ route('packs.server-properties', ['pack' => $pack, 'require' => 0]) }}" class="nr-btn-secondary !py-2 text-xs">{{ __('Download without require-resource-pack') }}</a>
                    </div>
                </div>
            @endif
        </div>

        <div class="nr-card p-5">
            <h2 class="nr-section-title !text-base">{{ __('Recent downloads') }}</h2>
            <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                @forelse ($downloads as $download)
                    <div class="flex items-center justify-between gap-3 py-2.5 text-xs">
                        <div class="min-w-0">
                            <p class="text-slate-700 dark:text-slate-300">{{ __('Version :number', ['number' => $download->version?->version_number ?? '—']) }}</p>
                            <p class="truncate text-slate-400">{{ $download->ip_hash ? substr($download->ip_hash, 0, 16).'…' : __('anonymous') }} · {{ $download->range_header ?: 'full' }}</p>
                        </div>
                        <div class="text-right text-slate-500 dark:text-slate-400">
                            <p>{{ $download->status_code }}</p>
                            <p>{{ $download->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No downloads recorded yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="nr-card mt-6 p-5">
        <h2 class="nr-section-title !text-base">{{ __('Edit details') }}</h2>
        <form method="POST" action="{{ route('packs.update', $pack) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
            @csrf
            @method('PATCH')
            <div>
                <label class="nr-label" for="edit-name">{{ __('Display name') }}</label>
                <input class="nr-input" id="edit-name" name="name" value="{{ old('name', $pack->name) }}" required maxlength="180">
            </div>
            <div class="sm:row-span-2">
                <label class="nr-label" for="edit-description">{{ __('Description') }}</label>
                <textarea class="nr-input h-full" id="edit-description" name="description" rows="4" maxlength="2000">{{ old('description', $pack->description) }}</textarea>
            </div>
            <div class="flex items-end">
                <button class="nr-btn-primary" type="submit">{{ __('Save changes') }}</button>
            </div>
        </form>
    </div>
@endsection
