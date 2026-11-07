@if ($paginator->hasPages())
    <nav class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('Pagination') }}">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-400 dark:border-slate-800 dark:text-slate-600">&laquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">&laquo;</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-sm text-slate-400">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white dark:bg-emerald-500 dark:text-slate-950">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">&raquo;</a>
            @else
                <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-400 dark:border-slate-800 dark:text-slate-600">&raquo;</span>
            @endif
        </div>
    </nav>
@endif
