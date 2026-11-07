@extends('layouts.app')

@section('title', __('Account settings'))
@section('content')
    <div class="mx-auto max-w-4xl">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ __('Account settings') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Manage your profile, language and password.') }}</p>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="nr-card p-5 sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Storage usage') }}</p>
                <p class="mt-2 text-xl font-bold text-slate-900 dark:text-slate-100">{{ nr_bytes((int) $usage['used']) }} <span class="text-sm font-medium text-slate-500">/ {{ nr_bytes((int) $usage['limit']) }}</span></p>
                <div class="nr-progress mt-3"><div class="nr-progress-bar" style="width: {{ $usage['percent'] }}%"></div></div>
            </div>
            <div class="nr-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Role') }}</p>
                <p class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ ucfirst(str_replace('_', ' ', $user->role->value)) }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Member since :date', ['date' => $user->created_at?->format('Y-m-d')]) }}</p>
            </div>
        </div>

        <div class="nr-card mt-6 p-6">
            <h2 class="nr-section-title">{{ __('Profile') }}</h2>
            <form method="POST" action="{{ route('account.profile') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PATCH')
                <div>
                    <label class="nr-label" for="username">{{ __('Username') }}</label>
                    <input class="nr-input" id="username" name="username" value="{{ old('username', $user->username) }}" required minlength="3" maxlength="64" pattern="[A-Za-z0-9_.\-]+">
                </div>
                <div>
                    <label class="nr-label" for="email">{{ __('Email address') }}</label>
                    <input class="nr-input" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="191">
                    <p class="nr-help">{{ __('Changing your email requires verification again.') }}</p>
                </div>
                <div>
                    <label class="nr-label" for="locale">{{ __('Interface language') }}</label>
                    <select class="nr-input" id="locale" name="locale">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}" @selected(old('locale', $user->locale) === $locale)>{{ $locale === 'zh_CN' ? '简体中文' : 'English' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="nr-label" for="timezone">{{ __('Timezone') }}</label>
                    <input class="nr-input" id="timezone" name="timezone" value="{{ old('timezone', $user->timezone) }}" placeholder="Asia/Shanghai">
                    <p class="nr-help">{{ __('Used for dates in the dashboard and logs.') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <button class="nr-btn-primary" type="submit">{{ __('Save profile') }}</button>
                </div>
            </form>
        </div>

        <div class="nr-card mt-4 p-6">
            <h2 class="nr-section-title">{{ __('Password') }}</h2>
            <form method="POST" action="{{ route('account.password') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PATCH')
                <div class="sm:col-span-2">
                    <label class="nr-label" for="current_password">{{ __('Current password') }}</label>
                    <input class="nr-input" id="current_password" name="current_password" type="password" required autocomplete="current-password">
                </div>
                <div>
                    <label class="nr-label" for="password">{{ __('New password') }}</label>
                    <input class="nr-input" id="password" name="password" type="password" required minlength="10" autocomplete="new-password">
                </div>
                <div>
                    <label class="nr-label" for="password_confirmation">{{ __('Confirm new password') }}</label>
                    <input class="nr-input" id="password_confirmation" name="password_confirmation" type="password" required minlength="10" autocomplete="new-password">
                </div>
                <div class="sm:col-span-2">
                    <button class="nr-btn-primary" type="submit">{{ __('Change password') }}</button>
                </div>
            </form>
        </div>

        <div class="nr-card mt-4 p-6">
            <h2 class="nr-section-title">{{ __('Session') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Sign out on this device. Other sessions remain active.') }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="nr-btn-secondary" type="submit">{{ __('Sign out') }}</button>
            </form>
        </div>
    </div>
@endsection
