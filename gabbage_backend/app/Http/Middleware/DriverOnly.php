<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DriverOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || $request->user()->role !== 'driver') {
            return response()->json([
                'status' => false,
                'error' => 'Unauthorized',
                'message' => 'Access denied. Driver role required.'
            ], 403);
        }

        return $next($request);
    }
}