@extends('layouts.app')
@section('title', __('Page not found'))
@section('content')
    <div class="mx-auto max-w-md py-20 text-center">
        <p class="text-6xl font-extrabold text-slate-200 dark:text-slate-800">404</p>
        <h1 class="mt-3 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ __('Page not found') }}</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('The page or download link you requested does not exist or has been removed.') }}</p>
        <a href="{{ route('home') }}" class="nr-btn-primary mt-5">{{ __('Back to home') }}</a>
    </div>
@endsection
