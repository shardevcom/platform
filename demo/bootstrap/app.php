<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Shardevcom\Security\Http\Middleware\SecurityHeadersMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // App-wide so even 401/404/429 responses carry the headers. The FQCN
        // (not the alias 'security.headers') because the global pipeline
        // instantiates middleware directly and cannot resolve aliases here.
        $middleware->prepend(SecurityHeadersMiddleware::class);
        // CORS (config/cors.php): must run before routes so preflight OPTIONS
        // short-circuits with 200 and never hits rate.limit/auth.
        $middleware->prepend(HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
