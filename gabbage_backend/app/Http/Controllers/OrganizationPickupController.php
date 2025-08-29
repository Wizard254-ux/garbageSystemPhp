<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pickup;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class OrganizationPickupController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'route_id' => 'nullable|exists:routes,id',
            'driver_id' => 'nullable|exists:users,id',
            'pickup_day' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'status' => 'nullable|in:picked,unpicked,scheduled',
            'date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $organizationId = auth()->user()->organization_id;
            $page = $request->get('page', 1);
            $limit = $request->get('limit', 50);
            
            $query = Pickup::with(['client.user', 'driver', 'route'])
                ->whereHas('client', function($q) use ($organizationId) {
                    $q->where('organization_id', $organizationId);
                });

            // Apply filters
            if ($request->route_id) {
                $query->where('route_id', $request->route_id);
            }

            if ($request->driver_id) {
                $query->where('driver_id', $request->driver_id);
            }

            if ($request->status) {
                $query->where('pickup_status', $request->status);
            }

            if ($request->date) {
                $query->whereDate('pickup_date', $request->date);
            }

            if ($request->pickup_day) {
                $query->whereHas('client', function($q) use ($request) {
                    $q->where('pickup_day', $request->pickup_day);
                });
            }

            $total = $query->count();
            $pickups = $query->orderBy('pickup_date', 'desc')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'pickups' => $pickups,
                    'pagination' => [
                        'currentPage' => $page,
                        'totalPages' => ceil($total / $limit),
                        'totalPickups' => $total,
                        'perPage' => $limit
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch pickups:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Failed to fetch pickups',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'driver_id' => 'nullable|exists:users,id',
            'pickup_date' => 'required|date',
            'status' => 'required|in:unpicked,picked,scheduled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $organizationId = auth()->user()->organization_id;
            
            // Verify client belongs to organization
            $client = Client::whereHas('user', function($q) use ($request, $organizationId) {
                $q->where('id', $request->client_id)
                  ->where('organization_id', $organizationId);
            })->first();

            if (!$client) {
                return response()->json([
                    'status' => false,
                    'error' => 'Client not found or not in your organization'
                ], 404);
            }

            $pickup = Pickup::create([
                'client_id' => $request->client_id,
                'route_id' => $client->route_id,
                'driver_id' => $request->driver_id,
                'pickup_status' => $request->status,
                'pickup_date' => $request->pickup_date,
                'picked_at' => $request->status === 'picked' ? now() : null
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Pickup created successfully',
                'data' => ['pickup' => $pickup->load(['client.user', 'driver', 'route'])]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to create pickup:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Failed to create pickup',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}