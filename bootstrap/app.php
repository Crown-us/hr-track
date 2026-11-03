<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PrivateAuthResponse;
use App\Http\Middleware\StagingNetwork;
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
        $middleware->append(StagingNetwork::class);
        $middleware->web(append: [EnsureAccountActive::class, PrivateAuthResponse::class, HandleInertiaRequests::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->home() ?? '/hr');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
