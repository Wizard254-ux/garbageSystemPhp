<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'error' => 'Unauthenticated',
                'message' => 'No authenticated user found. Please login again.'
            ], 401);
        }
        
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'status' => false,
                'error' => 'Forbidden',
                'message' => 'Admin access required. Current role: ' . $user->role
            ], 403);
        }
        
        return $next($request);
    }
}