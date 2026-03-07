<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ensure users are logged out if their session was invalidated by a newer login.
        $middleware->web(append: [
            App\Http\Middleware\EnsureActiveSession::class,
            App\Http\Middleware\EnsureApiTokenInSession::class,
        ]);
        
        $middleware->alias([
            'permission' => App\Http\Middleware\CheckPermission::class,
            'role' => App\Http\Middleware\CheckRole::class,
            'warehouse' => App\Http\Middleware\CheckWarehouseAccess::class,
            'active.user' => App\Http\Middleware\EnsureActiveUser::class,
            'update.activity' => App\Http\Middleware\UpdateLastActivity::class,
            'auto.warehouse' => App\Http\Middleware\AutoWarehouseRedirect::class,
            'attendance.agent' => App\Http\Middleware\VerifyAttendanceAgentSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();