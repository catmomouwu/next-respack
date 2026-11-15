<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Services\ResourcePack\QuotaService;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request, QuotaService $quota): View
    {
        return view('account.edit', [
            'user' => $request->user(),
            'usage' => $quota->usage($request->user()),
            'locales' => SetLocale::SUPPORTED,
        ]);
    }

    public function updateProfile(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_\-\.]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['nullable', Rule::in(SetLocale::SUPPORTED)],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        $emailChanged = strtolower($data['email']) !== $user->email;

        $user->fill([
            'username' => $data['username'],
            'email' => strtolower($data['email']),
            'locale' => $data['locale'] ?? $user->locale,
            'timezone' => $data['timezone'] ?? $user->timezone,
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();
        $request->session()->put('locale', $user->locale);
        $audit->log('account.profile_updated', $user, __('Account profile updated.'), ['email_changed' => $emailChanged]);

        if ($emailChanged && nr_setting_bool('email_verification', false)) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);
            }

            return redirect()->route('verification.notice')->with('status', __('Please verify your new email address.'));
        }

        return back()->with('status', __('Profile updated.'));
    }

    public function updatePassword(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'max:191', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'current_password' => __('The current password is incorrect.'),
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        $audit->log('account.password_changed', $user, __('Account password changed.'));

        $request->session()->regenerate();

        return back()->with('status', __('Password changed.'));
    }
}
