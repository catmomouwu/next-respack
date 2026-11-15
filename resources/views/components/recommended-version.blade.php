@php
    $recommendation = $version?->suggestedVersion();
    $compact = $compact ?? false;
@endphp

@if ($recommendation)
    <p class="{{ $compact ? 'text-xs' : 'text-sm' }} font-semibold text-slate-800 dark:text-slate-200">
        {{ __('Suggested version: :version', ['version' => $recommendation['version_name']]) }}
    </p>
    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
        {{ __('Version type') }}: {{ \App\Models\ResourcePackVersion::versionTypeLabel($recommendation['version_type']) }}
    </p>
    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
        Resource Pack Format:
        <span class="font-mono">{{ $recommendation['format_value'] ?? ($version?->formatLabel() ?? '—') }}</span>
    </p>
    @if (! $compact && ! empty($recommendation['compatible_versions']))
        @php
            $compatible = array_slice(array_column($recommendation['compatible_versions'], 'version_name'), 0, 8);
        @endphp
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ __('Other compatible versions') }}: {{ implode(', ', $compatible) }}@if (count($recommendation['compatible_versions']) > 8) …@endif
        </p>
    @endif
@else
    <p class="{{ $compact ? 'text-xs' : 'text-sm' }} font-semibold text-slate-500 dark:text-slate-400">
        {{ __('Unable to determine a suggested version.') }}
    </p>
@endif

@unless ($compact)
    <p class="mt-2 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
        {{ __('Format compatibility does not guarantee that all textures, models, sounds and other content in the pack are fully compatible.') }}
    </p>
@endunless
