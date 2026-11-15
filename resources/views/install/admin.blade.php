@extends('layouts.install')

@section('title', __('Create administrator'))
@section('heading', __('Create the first administrator'))
@section('subheading', __('This account receives the super administrator role. No default account will be created after installation.'))

@section('steps')
    @include('components.install-steps', ['current' => 5])
@endsection

@section('content')
    <form method="POST" action="{{ route('install.admin.save') }}" autocomplete="off">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="nr-label" for="username">{{ __('Username') }}</label>
                <input class="nr-input" id="username" name="username" value="{{ old('username', $data['username'] ?? 'admin') }}" required minlength="3" maxlength="64" pattern="[A-Za-z0-9_.\-]+">
                <p class="nr-help">{{ __('3–64 characters: letters, numbers, underscore, dot or dash.') }}</p>
            </div>
            <div>
                <label class="nr-label" for="email">{{ __('Email address') }}</label>
                <input class="nr-input" id="email" name="email" type="email" value="{{ old('email', $data['email'] ?? '') }}" required maxlength="191">
            </div>
            <div>
                <label class="nr-label" for="password">{{ __('Password') }}</label>
                <input class="nr-input" id="password" name="password" type="password" required minlength="10" maxlength="191" autocomplete="new-password">
                <p class="nr-help">{{ __('At least 10 characters. Use a unique, strong password.') }}</p>
            </div>
            <div>
                <label class="nr-label" for="password_confirmation">{{ __('Confirm password') }}</label>
                <input class="nr-input" id="password_confirmation" name="password_confirmation" type="password" required minlength="10" autocomplete="new-password">
            </div>
        </div>

        <div class="nr-warning mt-6">
            {{ __('This account can manage all users, storage and system settings. It is granted the Super Administrator role immediately.') }}
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('install.site') }}" class="nr-btn-ghost">{{ __('Back') }}</a>
            <button type="submit" class="nr-btn-primary">{{ __('Save and continue') }}</button>
        </div>
    </form>
@endsection
