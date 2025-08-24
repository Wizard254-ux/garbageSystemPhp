<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'status' => false,
                'error' => 'Unauthorized',
                'message' => 'Access token is required'
            ], 403);
        }
        
        return $next($request);
    }
}