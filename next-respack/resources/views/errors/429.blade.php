@extends('layouts.app')
@section('title', __('Too many requests'))
@section('content')
    <div class="mx-auto max-w-md py-20 text-center">
        <p class="text-6xl font-extrabold text-slate-200 dark:text-slate-800">429</p>
        <h1 class="mt-3 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ __('Too many requests') }}</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Please wait a moment before trying again.') }}</p>
        <a href="{{ route('home') }}" class="nr-btn-primary mt-5">{{ __('Back to home') }}</a>
    </div>
@endsection
