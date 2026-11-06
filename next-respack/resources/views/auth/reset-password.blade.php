@extends('layouts.app')

@section('title', __('Reset password'))
@section('content')
    <div class="mx-auto max-w-md py-10">
        <div class="nr-card p-7">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Reset your password') }}</h1>
            <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ __('Choose a new password for your account.') }}</p>

            <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label class="nr-label" for="email">{{ __('Email address') }}</label>
                    <input class="nr-input" id="email" name="email" type="email" value="{{ $email }}" required autocomplete="email">
                </div>
                <div>
                    <label class="nr-label" for="password">{{ __('New password') }}</label>
                    <input class="nr-input" id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
                </div>
                <div>
                    <label class="nr-label" for="password_confirmation">{{ __('Confirm new password') }}</label>
                    <input class="nr-input" id="password_confirmation" name="password_confirmation" type="password" required minlength="10" autocomplete="new-password">
                </div>
                <button type="submit" class="nr-btn-primary w-full !py-3">{{ __('Reset password') }}</button>
            </form>
        </div>
    </div>
@endsection
