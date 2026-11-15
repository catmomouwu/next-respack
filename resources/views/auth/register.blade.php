@extends('layouts.app')

@section('title', __('Create account'))
@section('content')
    <div class="mx-auto max-w-lg py-10">
        <div class="nr-card p-7">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Create an account') }}</h1>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ __('Upload and share Minecraft Java Edition resource packs.') }}</p>

            <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="nr-label" for="username">{{ __('Username') }}</label>
                    <input class="nr-input" id="username" name="username" value="{{ old('username') }}" required minlength="3" maxlength="64" pattern="[A-Za-z0-9_.\-]+" autocomplete="username">
                    <p class="nr-help">{{ __('3–64 characters: letters, numbers, underscore, dot or dash.') }}</p>
                </div>
                <div>
                    <label class="nr-label" for="email">{{ __('Email address') }}</label>
                    <input class="nr-input" id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="191" autocomplete="email">
                </div>
                <div>
                    <label class="nr-label" for="password">{{ __('Password') }}</label>
                    <input class="nr-input" id="password" name="password" type="password" required minlength="10" maxlength="191" autocomplete="new-password">
                    <p class="nr-help">{{ __('At least 10 characters.') }}</p>
                </div>
                <div>
                    <label class="nr-label" for="password_confirmation">{{ __('Confirm password') }}</label>
                    <input class="nr-input" id="password_confirmation" name="password_confirmation" type="password" required minlength="10" autocomplete="new-password">
                </div>
                <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="terms" value="1" class="mt-0.5 rounded text-emerald-600" required @checked(old('terms'))>
                    <span>{{ __('I agree to upload only content I have the right to distribute and to follow the site rules.') }}</span>
                </label>
                <button type="submit" class="nr-btn-primary w-full !py-3">{{ __('Create account') }}</button>
            </form>

            <p class="mt-5 border-t border-slate-200 pt-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                {{ __('Already have an account?') }}
                <a href="{{ route('login') }}" class="nr-link">{{ __('Sign in') }}</a>
            </p>
        </div>
    </div>
@endsection
