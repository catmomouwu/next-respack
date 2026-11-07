@extends('layouts.app')
@section('title', __('Service unavailable'))
@section('content')
    <div class="mx-auto max-w-md py-20 text-center">
        <p class="text-6xl font-extrabold text-slate-200 dark:text-slate-800">503</p>
        <h1 class="mt-3 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ __('Service unavailable') }}</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Next Respack is not installed or is under maintenance.') }}</p>
        <a href="{{ route('install.welcome') }}" class="nr-btn-primary mt-5">{{ __('Open installer') }}</a>
    </div>
@endsection
