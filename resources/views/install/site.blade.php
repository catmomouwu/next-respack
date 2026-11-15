@extends('layouts.install')

@section('title', __('Site configuration'))
@section('heading', __('Site configuration'))
@section('subheading', __('Basic website settings. You can change all of these later in the admin panel.'))

@section('steps')
    @include('components.install-steps', ['current' => 4])
@endsection

@section('content')
    <form method="POST" action="{{ route('install.site.save') }}">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="nr-label" for="name">{{ __('Site name') }}</label>
                <input class="nr-input" id="name" name="name" value="{{ old('name', $data['name'] ?? 'Next Respack') }}" required maxlength="120">
            </div>
            <div>
                <label class="nr-label" for="url">{{ __('Site URL') }}</label>
                <input class="nr-input" id="url" name="url" type="url" value="{{ old('url', $data['url'] ?? '') }}" required>
                <p class="nr-help">{{ __('Public HTTPS URL without a trailing slash. Example: https://respack.example.com') }}</p>
            </div>
            <div class="sm:col-span-2">
                <label class="nr-label" for="description">{{ __('Site description') }}</label>
                <textarea class="nr-input" id="description" name="description" rows="2" maxlength="500">{{ old('description', $data['description'] ?? '') }}</textarea>
            </div>
            <div>
                <label class="nr-label" for="locale">{{ __('Default language') }}</label>
                <select class="nr-input" id="locale" name="locale">
                    @foreach (($locales ?? ['zh_CN', 'en']) as $locale)
                        <option value="{{ $locale }}" @selected(old('locale', $data['locale'] ?? 'zh_CN') === $locale)>{{ $locale === 'zh_CN' ? '简体中文' : 'English' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="nr-label" for="timezone">{{ __('Timezone') }}</label>
                <input class="nr-input" id="timezone" name="timezone" value="{{ old('timezone', $data['timezone'] ?? 'Asia/Shanghai') }}" required>
                <p class="nr-help">{{ __('PHP timezone identifier, for example Asia/Shanghai or UTC.') }}</p>
            </div>
            <div>
                <label class="nr-label" for="max_upload_mb">{{ __('Maximum upload size (MB)') }}</label>
                <input class="nr-input" id="max_upload_mb" name="max_upload_mb" type="number" min="1" max="1024" value="{{ old('max_upload_mb', $data['max_upload_mb'] ?? 512) }}" required>
                <p class="nr-help">{{ __('Must also be allowed by PHP upload_max_filesize and post_max_size.') }}</p>
            </div>
            <div>
                <label class="nr-label" for="default_quota_mb">{{ __('Default user storage quota (MB)') }}</label>
                <input class="nr-input" id="default_quota_mb" name="default_quota_mb" type="number" min="64" max="10240" value="{{ old('default_quota_mb', $data['default_quota_mb'] ?? 2048) }}" required>
            </div>
            <div>
                <label class="nr-label" for="registration_quota_mb">{{ __('Quota for new registrations (MB)') }}</label>
                <input class="nr-input" id="registration_quota_mb" name="registration_quota_mb" type="number" min="64" max="10240" value="{{ old('registration_quota_mb', $data['registration_quota_mb'] ?? 1024) }}" required>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <input type="checkbox" name="allow_registration" value="1" class="mt-0.5 rounded text-emerald-600" @checked(old('allow_registration', $data['allow_registration'] ?? true))>
                <span>
                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Allow public registration') }}</span>
                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ __('Disable to make the site invitation-only.') }}</span>
                </span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <input type="checkbox" name="email_verification" value="1" class="mt-0.5 rounded text-emerald-600" @checked(old('email_verification', $data['email_verification'] ?? false))>
                <span>
                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Require email verification') }}</span>
                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ __('Configure SMTP in the admin panel before enabling this.') }}</span>
                </span>
            </label>
        </div>

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('install.storage') }}" class="nr-btn-ghost">{{ __('Back') }}</a>
            <button type="submit" class="nr-btn-primary">{{ __('Save and continue') }}</button>
        </div>
    </form>
@endsection
