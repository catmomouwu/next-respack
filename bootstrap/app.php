<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\EnsureInstalled::class);
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'install.not' => \App\Http\Middleware\EnsureNotInstalled::class,
            'active' => \App\Http\Middleware\EnsureActive::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'verified.custom' => \App\Http\Middleware\EnsureEmailVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
