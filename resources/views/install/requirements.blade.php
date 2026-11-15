@extends('layouts.install')

@section('title', __('Environment check'))
@section('heading', __('Environment check'))
@section('subheading', __('Next Respack verified your server configuration. Required checks must pass before continuing.'))
@section('steps')
    @include('components.install-steps', ['current' => 1])
@endsection

@section('content')
    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        @foreach ([
            ['label' => __('Passed'), 'value' => $summary['pass'], 'class' => 'text-emerald-700 dark:text-emerald-400'],
            ['label' => __('Warnings'), 'value' => $summary['warn'], 'class' => 'text-amber-700 dark:text-amber-400'],
            ['label' => __('Failed'), 'value' => $summary['fail'], 'class' => 'text-red-700 dark:text-red-400'],
        ] as $card)
            <div class="nr-card-muted p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-1 text-2xl font-bold {{ $card['class'] }}">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="nr-card overflow-hidden">
        <div class="nr-scrollbar-thin max-h-[28rem] overflow-auto">
            <table class="nr-table">
                <thead class="sticky top-0 bg-white dark:bg-slate-900">
                    <tr>
                        <th class="nr-th">{{ __('Check') }}</th>
                        <th class="nr-th">{{ __('Current value') }}</th>
                        <th class="nr-th">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td class="nr-td">
                                <p class="font-medium text-slate-800 dark:text-slate-200">{{ $item['label'] }}</p>
                                @if ($item['status'] !== 'pass')
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $item['fix'] }}</p>
                                @endif
                            </td>
                            <td class="nr-td font-mono text-xs text-slate-600 dark:text-slate-300">{{ $item['value'] }}</td>
                            <td class="nr-td">
                                @if ($item['status'] === 'pass')
                                    <span class="nr-badge-green">{{ __('Passed') }}</span>
                                @elseif ($item['status'] === 'warn')
                                    <span class="nr-badge-amber">{{ __('Warning') }}</span>
                                @else
                                    <span class="nr-badge-red">{{ $item['required'] ? __('Failed') : __('Warning') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('install.welcome') }}" class="nr-btn-ghost">{{ __('Back') }}</a>
        <div class="flex items-center gap-3">
            <a href="{{ route('install.requirements') }}" class="nr-btn-secondary">{{ __('Re-check') }}</a>
            <form method="POST" action="{{ route('install.requirements.save') }}">
                @csrf
                <button type="submit" class="nr-btn-primary" @disabled(! $passed)>
                    {{ __('Continue') }}
                </button>
            </form>
        </div>
    </div>
    @if (! $passed)
        <p class="mt-4 text-sm text-red-600 dark:text-red-400">{{ __('Fix all required failures, then re-check the environment.') }}</p>
    @endif
@endsection
