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
        //
        $middleware->alias([
        'file.uploads' => \App\Http\Middleware\HandleFileUploads::class,
        'admin.only' => \App\Http\Middleware\AdminOnly::class,
        'organization.only' => \App\Http\Middleware\OrganizationOnly::class,
    ]);
        
        // Configure API authentication to return JSON
        $middleware->redirectGuestsTo(function ($request) {
            return response()->json([
                'status' => false,
                'error' => 'Unauthorized',
                'message' => 'Access token is required'
            ], 401);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
