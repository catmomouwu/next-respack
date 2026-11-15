@extends('layouts.app')

@section('title', __('Forgot password'))
@section('content')
    <div class="mx-auto max-w-md py-10">
        <div class="nr-card p-7">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Forgot your password?') }}</h1>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ __('Enter your email address and we will send a reset link if the account exists.') }}</p>

            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="nr-label" for="email">{{ __('Email address') }}</label>
                    <input class="nr-input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                </div>
                <button type="submit" class="nr-btn-primary w-full !py-3">{{ __('Send reset link') }}</button>
            </form>

            <p class="mt-5 border-t border-slate-200 pt-4 text-sm dark:border-slate-800">
                <a href="{{ route('login') }}" class="text-slate-500 hover:text-emerald-600 dark:text-slate-400">{{ __('Back to sign in') }}</a>
            </p>
        </div>
    </div>
@endsection
