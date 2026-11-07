<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['zh_CN', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = $request->user()?->locale;
        }

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = (string) config('app.locale');
        }

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'zh_CN';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
