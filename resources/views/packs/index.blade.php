@extends('layouts.app')

@section('title', __('My resource packs'))
@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('My resource packs') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Manage versions, download links and server.properties snippets.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('packs.index') }}" class="flex items-center gap-2">
                <input class="nr-input !w-56" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search packs…') }}">
                <button class="nr-btn-secondary" type="submit">{{ __('Search') }}</button>
            </form>
            <a href="{{ route('packs.create') }}" class="nr-btn-primary">{{ __('Upload') }}</a>
        </div>
    </div>

    @if ($packs->isEmpty())
        <div class="nr-card mt-6 p-12 text-center">
            <p class="text-lg font-semibold text-slate-800 dark:text-slate-200">{{ __('No resource packs yet') }}</p>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">{{ __('Upload a Minecraft Java Edition resource pack ZIP. We will validate the archive, read pack.mcmeta, detect the pack format and compute SHA-1.') }}</p>
            <a href="{{ route('packs.create') }}" class="nr-btn-primary mt-5">{{ __('Upload your first resource pack') }}</a>
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($packs as $pack)
                <div class="nr-card flex flex-col p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('packs.show', $pack) }}" class="block truncate font-semibold text-slate-900 hover:text-emerald-600 dark:text-slate-100 dark:hover:text-emerald-400">{{ $pack->name }}</a>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __(':count versions', ['count' => $pack->versions_count]) }} · {{ $pack->created_at?->format('Y-m-d') }}</p>
                        </div>
                        <span class="nr-badge shrink-0 {{ $pack->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                            {{ $pack->status === 'active' ? __('Active') : __('Disabled') }}
                        </span>
                    </div>

                    @if ($pack->description)
                        <p class="mt-3 line-clamp-2 text-sm text-slate-600 dark:text-slate-300">{{ $pack->description }}</p>
                    @endif

                    <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-center dark:bg-slate-900/60">
                        <div>
                            <p class="font-mono text-sm font-bold text-slate-800 dark:text-slate-200">{{ $pack->currentVersion?->formatLabel() ?? '—' }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('Format') }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ nr_bytes((int) $pack->totalSize()) }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('Size') }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ number_format((int) $pack->totalDownloads()) }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('Downloads') }}</p>
                        </div>
                    </div>

                    <div class="mt-auto flex items-center gap-2 pt-4">
                        <a href="{{ route('packs.show', $pack) }}" class="nr-btn-secondary flex-1 !py-2 text-xs">{{ __('Manage') }}</a>
                        <a href="{{ route('packs.versions.create', $pack) }}" class="nr-btn-primary !py-2 text-xs">{{ __('New version') }}</a>
                    </div>
                </div>
            @endforeach
        </div>

        {{ $packs->links() }}
    @endif
@endsection
