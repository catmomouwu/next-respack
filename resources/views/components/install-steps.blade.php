@props(['current' => 0])
@php
    $steps = [
        0 => __('Welcome'),
        1 => __('Environment'),
        2 => __('Database'),
        3 => __('Storage'),
        4 => __('Site'),
        5 => __('Administrator'),
        6 => __('Confirm'),
        7 => __('Install'),
    ];
@endphp
<div class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white/80 px-3 py-3 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
    @foreach ($steps as $index => $label)
        @if ($index > 0)
            <span class="text-slate-300 dark:text-slate-700">/</span>
        @endif
        <span class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium
            {{ $index === $current ? 'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-slate-950' : ($index < $current ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500') }}">
            <span class="flex h-5 w-5 items-center justify-center rounded-full border text-[10px] font-bold
                {{ $index === $current ? 'border-white/40' : ($index < $current ? 'border-emerald-300 dark:border-emerald-700' : 'border-slate-200 dark:border-slate-700') }}">
                {{ $index }}
            </span>
            {{ $label }}
        </span>
    @endforeach
</div>
