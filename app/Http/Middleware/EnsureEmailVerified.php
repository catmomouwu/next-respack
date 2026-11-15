<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! nr_setting_bool('email_verification', false)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Your email address must be verified.')], 403);
        }

        return redirect()->route('verification.notice');
    }
}
