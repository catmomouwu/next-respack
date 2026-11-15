@extends('layouts.app')

@section('title', __('Sign in'))
@section('content')
    <div class="mx-auto max-w-md py-10">
        <div class="nr-card p-7">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Sign in') }}</h1>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ __('Use your username or email address.') }}</p>

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="nr-label" for="login">{{ __('Username or email') }}</label>
                    <input class="nr-input" id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
                </div>
                <div>
                    <label class="nr-label" for="password">{{ __('Password') }}</label>
                    <input class="nr-input" id="password" name="password" type="password" required autocomplete="current-password">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="remember" value="1" class="rounded text-emerald-600" @checked(old('remember'))>
                    {{ __('Remember me') }}
                </label>
                <button type="submit" class="nr-btn-primary w-full !py-3">{{ __('Sign in') }}</button>
            </form>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-4 text-sm dark:border-slate-800">
                <a href="{{ route('password.request') }}" class="text-slate-500 hover:text-emerald-600 dark:text-slate-400">{{ __('Forgot your password?') }}</a>
                @if (nr_setting_bool('allow_registration', true))
                    <a href="{{ route('register') }}" class="nr-link">{{ __('Create an account') }}</a>
                @endif
            </div>
        </div>
    </div>
@endsection
