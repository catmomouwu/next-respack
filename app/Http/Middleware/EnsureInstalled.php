<?php

namespace App\Http\Middleware;

use App\Services\Installation\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function __construct(private readonly InstallState $state)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->installed()) {
            return $next($request);
        }

        if ($request->is('install', 'install/*')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => __('Next Respack is not installed yet.'),
                'install_url' => url('/install'),
            ], 503);
        }

        return redirect()->route('install.welcome');
    }
}
