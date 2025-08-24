<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganizationOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->role !== 'organization') {
            return response()->json([
                'status' => false,
                'error' => 'Unauthorized',
                'message' => 'Only organizations can access this resource'
            ], 403);
        }

        return $next($request);
    }
}
