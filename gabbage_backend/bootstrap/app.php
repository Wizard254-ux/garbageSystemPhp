<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',  
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Daily invoice generation
        $schedule->command('invoices:generate-monthly')
                 ->everyMinute()
                 ->withoutOverlapping();
                 
        // Daily pickup scheduling
        $schedule->command('pickups:schedule-daily')
                 ->daily()
                 ->at('00:01')
                 ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
        $middleware->alias([
        'file.uploads' => \App\Http\Middleware\HandleFileUploads::class,
        'admin.only' => \App\Http\Middleware\AdminOnly::class,
        'organization.only' => \App\Http\Middleware\OrganizationOnly::class,
        'driver.only' => \App\Http\Middleware\DriverOnly::class,
    ]);
        
        // Configure API authentication to return JSON
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null; // Let exception handler deal with it
            }
            return '/login'; // For web routes only
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Single handler for all authentication issues
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                // Handle all authentication-related exceptions
                if ($e instanceof \Illuminate\Auth\AuthenticationException ||
                    $e instanceof \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException ||
                    ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode() === 401) ||
                    str_contains($e->getMessage(), 'Unauthenticated') ||
                    str_contains($e->getMessage(), 'token')) {
                    
                    $message = 'Invalid or expired token';
                    if (str_contains($e->getMessage(), 'required') || !$request->bearerToken()) {
                        $message = 'Access token is required';
                    }
                    
                    return response()->json([
                        'status' => false,
                        'error' => 'Unauthorized',
                        'message' => $message
                    ], 401);
                }
                
                // Handle permission exceptions
                if ($e instanceof \Laravel\Sanctum\Exceptions\MissingAbilityException) {
                    return response()->json([
                        'status' => false,
                        'error' => 'Forbidden',
                        'message' => 'Insufficient permissions'
                    ], 403);
                }
            }
        });
    })->create();
