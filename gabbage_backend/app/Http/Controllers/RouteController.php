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
        $routes = Route::where('organization_id', $organizationId)->with('activeDriver')->get();
        
        return response()->json([
            'status' => true,
            'data' => ['data' => $routes]
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
        $route = Route::where('organization_id', $organizationId)->with('activeDriver')->find($id);

        if (!$route) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Route not found'
            ], 404);
        }

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

    public function getDriverRoutes(Request $request)
    {
        try {
            $driverId = $request->user()->id;
            $organizationId = $request->user()->organization_id;
            
            // Get all routes for the driver's organization
            $routes = Route::where('organization_id', $organizationId)
                ->where('isActive', true)
                ->select('id', 'name', 'path', 'description')
                ->get();
            
            return response()->json([
                'status' => true,
                'data' => ['routes' => $routes]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Failed to get routes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getDriverRouteDetails(Request $request, $id)
    {
        try {
            $driverId = $request->user()->id;
            $organizationId = $request->user()->organization_id;
            
            // Get route details for the driver's organization
            $route = Route::where('organization_id', $organizationId)
                ->where('id', $id)
                ->first();
            
            if (!$route) {
                return response()->json([
                    'status' => false,
                    'error' => 'Not found',
                    'message' => 'Route not found'
                ], 404);
            }
            
            return response()->json([
                'status' => true,
                'data' => ['route' => $route]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Failed to get route details',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}