<?php

namespace App\Http\Controllers;

use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = $request->user()->id;
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $search = $request->get('search', '');
        
        $query = Route::where('organization_id', $organizationId);
        
        // Add search functionality
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('path', 'LIKE', '%' . $search . '%')
                  ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }
        
        // Get total count for pagination
        $totalRoutes = $query->count();
        $totalPages = ceil($totalRoutes / $limit);
        
        // Apply pagination and ordering
        $routes = $query->orderBy('created_at', 'desc')
                       ->skip(($page - 1) * $limit)
                       ->take($limit)
                       ->get();
        
        // Add active drivers and client count information for each route
        $routes->each(function ($route) {
            $activeDrivers = \App\Models\DriverRoute::with('driver')
                ->where('route_id', $route->id)
                ->where('is_active', true)
                ->get();
            
            $route->active_drivers = $activeDrivers->map(function ($driverRoute) {
                return [
                    'id' => $driverRoute->driver->id,
                    'name' => $driverRoute->driver->name,
                    'activated_at' => $driverRoute->activated_at
                ];
            });
            
            // Add client count for this route
            $route->clients_count = \App\Models\Client::where('route_id', $route->id)->count();
        });
        
        return response()->json([
            'status' => true,
            'data' => [
                'data' => $routes,
                'pagination' => [
                    'currentPage' => (int)$page,
                    'totalPages' => $totalPages,
                    'totalItems' => $totalRoutes,
                    'itemsPerPage' => (int)$limit,
                    'hasNextPage' => $page < $totalPages,
                    'hasPrevPage' => $page > 1
                ]
            ]
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'path' => 'required|string',
            'description' => 'nullable|string',
            'isActive' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        $route = Route::create([
            'name' => $request->name,
            'path' => $request->path,
            'description' => $request->description,
            'isActive' => $request->isActive ?? true,
            'organization_id' => $request->user()->id
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Route created successfully',
            'data' => ['route' => $route]
        ], 200);
    }

    public function show(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $route = Route::where('organization_id', $organizationId)->find($id);

        if (!$route) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Route not found'
            ], 404);
        }
        
        // Add active drivers information
        $activeDrivers = \App\Models\DriverRoute::with('driver')
            ->where('route_id', $route->id)
            ->where('is_active', true)
            ->get();
        
        $route->active_drivers = $activeDrivers->map(function ($driverRoute) {
            return [
                'id' => $driverRoute->driver->id,
                'name' => $driverRoute->driver->name,
                'email' => $driverRoute->driver->email,
                'phone' => $driverRoute->driver->phone,
                'activated_at' => $driverRoute->activated_at
            ];
        });
        
        // Add clients information
        $clients = \App\Models\Client::with('user')
            ->where('route_id', $route->id)
            ->get();
            
        $route->clients = $clients->map(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->user->name,
                'email' => $client->user->email,
                'phone' => $client->user->phone,
                'address' => $client->user->adress,
                'clientType' => $client->clientType,
                'monthlyRate' => $client->monthlyRate,
                'pickUpDay' => $client->pickUpDay
            ];
        });
        
        $route->clients_count = $clients->count();

        return response()->json([
            'status' => true,
            'data' => ['route' => $route]
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $route = Route::where('organization_id', $organizationId)->find($id);

        if (!$route) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Route not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'path' => 'string',
            'description' => 'nullable|string',
            'isActive' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        $route->update($request->only(['name', 'path', 'description', 'isActive']));

        return response()->json([
            'status' => true,
            'message' => 'Route updated successfully',
            'data' => ['route' => $route->fresh()]
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $route = Route::where('organization_id', $organizationId)->find($id);

        if (!$route) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Route not found'
            ], 404);
        }

        $route->delete();

        return response()->json([
            'status' => true,
            'message' => 'Route deleted successfully'
        ], 200);
    }
}