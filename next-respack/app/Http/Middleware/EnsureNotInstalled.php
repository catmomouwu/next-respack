<?php

namespace App\Http\Middleware;

use App\Services\Installation\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function __construct(private readonly InstallState $state)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->installed()) {
            abort(404);
        }

        return $next($request);
    }
}
