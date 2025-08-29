<?php

namespace App\Http\Controllers;

use App\Models\Pickup;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PickupController extends Controller
{
    public function markPickup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickup_id' => 'required|integer',
            'status' => 'required|in:picked'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid input data',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 422);
        }

        try {
            $driverId = $request->user()->id;
            $clientId = $request->pickup_id; // This is actually the client ID from the frontend
            $today = Carbon::now();
            $weekStart = $today->copy()->startOfWeek();
            $weekEnd = $today->copy()->endOfWeek();

           

            // Get client details - try both user_id and id fields
            $client = Client::where('user_id', $clientId)
                ->orWhere('id', $clientId)
                ->first();
                
            if (!$client) {
                // If not found in clients table, check if it's a user ID directly
                $user = \App\Models\User::where('id', $clientId)
                    ->where('role', 'client')
                    ->first();
                    
                if ($user) {
                    // Find client record by user_id
                    $client = Client::where('user_id', $user->id)->first();
                }
            }
            
            if (!$client) {
                \Log::error('Client not found:', [
                    'lookup_id' => $clientId,
                    'tried_user_id' => true,
                    'tried_client_id' => true
                ]);
                
                return response()->json([
                    'status' => false,
                    'error' => 'Client not found',
                    'message' => 'Client record not found for ID: ' . $clientId
                ], 404);
            }
            
            // Use the actual user_id for pickup operations
            $clientUserId = $client->user_id;

            

            // Check if driver is active on the client's route
            $driverRouteActive = \App\Models\DriverRoute::where('driver_id', $driverId)
                ->where('route_id', $client->route_id)
                ->where('is_active', true)
                ->first();

            if (!$driverRouteActive) {
               
                return response()->json([
                    'status' => false,
                    'error' => 'Not active on route',
                    'message' => 'Driver is not active on the route for this client'
                ], 403);
            }

           

            // Check if pickup already exists for this week
            $existingPickup = Pickup::where('client_id', $clientUserId)
                ->whereBetween('pickup_date', [$weekStart, $weekEnd])
                ->first();

            if ($existingPickup) {
                if ($existingPickup->pickup_status === 'picked') {
                    \Log::warning('Client already picked this week:', [
                        'pickup_id' => $existingPickup->id,
                        'picked_at' => $existingPickup->picked_at
                    ]);
                    return response()->json([
                        'status' => false,
                        'error' => 'Already picked',
                        'message' => 'Client has already been picked up this week'
                    ], 400);
                }

                // Update existing pickup
                $existingPickup->update([
                    'driver_id' => $driverId,
                    'pickup_status' => 'picked',
                    'pickup_date' => $today->toDateString(),
                    'picked_at' => $today
                ]);

                \Log::info('Updated existing pickup:', [
                    'pickup_id' => $existingPickup->id,
                    'status' => 'picked'
                ]);

                // Send pickup completion email to client
                try {
                    \Log::info('Sending pickup completion email to client:', ['email' => $existingPickup->client->email]);
                    \Mail::to($existingPickup->client->email)->send(new \App\Mail\PickupCompleted($existingPickup->load(['client', 'driver', 'route'])));
                    \Log::info('Pickup completion email sent successfully');
                } catch (\Exception $e) {
                    \Log::error('Failed to send pickup completion email:', [
                        'error' => $e->getMessage(),
                        'client_email' => $existingPickup->client->email
                    ]);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Pickup completed successfully',
                    'data' => ['pickup' => $existingPickup->fresh()]
                ], 200);
            }

            // Create new pickup record
            $pickup = Pickup::create([
                'client_id' => $clientUserId,
                'route_id' => $client->route_id,
                'driver_id' => $driverId,
                'pickup_status' => 'picked',
                'pickup_date' => $today->toDateString(),
                'picked_at' => $today
            ]);

            \Log::info('Created new pickup:', [
                'pickup_id' => $pickup->id,
                'client_id' => $clientUserId,
                'original_lookup_id' => $clientId,
                'driver_id' => $driverId,
                'pickup_date' => $pickup->pickup_date
            ]);

            // Send pickup completion email to client
            try {
                \Log::info('Sending pickup completion email to client:', ['email' => $pickup->client->email]);
                \Mail::to($pickup->client->email)->send(new \App\Mail\PickupCompleted($pickup->load(['client', 'driver', 'route'])));
                \Log::info('Pickup completion email sent successfully');
            } catch (\Exception $e) {
                \Log::error('Failed to send pickup completion email:', [
                    'error' => $e->getMessage(),
                    'client_email' => $pickup->client->email
                ]);
            }

            \Log::info('=== DRIVER PICKUP END - SUCCESS ===');

            return response()->json([
                'status' => true,
                'message' => 'Pickup completed successfully',
                'data' => ['pickup' => $pickup->load(['client', 'route'])]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Pickup failed:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Pickup failed',
                'message' => 'Failed to record pickup: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPickups(Request $request)
    {
        \Log::info('=== GET PICKUPS START ===');
        \Log::info('Request params:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:picked,unpicked,skipped,scheduled',
            'week' => 'nullable|in:current,this',
            'driver_id' => 'nullable|string',
            'route_id' => 'nullable|exists:routes,id',
            'pickup_day' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'client_name' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        try {
            $today = Carbon::now()->toDateString();
            $requestedDate = $request->date ?? $today;
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $status = $request->status;
            $week = $request->week;
            
            // Handle week filter
            if ($week === 'current' || $week === 'this') {
                $weekStart = Carbon::now()->startOfWeek()->toDateString();
                $weekEnd = Carbon::now()->endOfWeek()->toDateString();
                $startDate = $weekStart;
                $endDate = $weekEnd;
                \Log::info('Week filter applied:', ['week_start' => $weekStart, 'week_end' => $weekEnd]);
            }
            
            \Log::info('Pickup query parameters:', [
                'today' => $today,
                'requested_date' => $requestedDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status
            ]);

            // For today's unpicked or week filter - check clients who should be picked but aren't in pickup table
            // Only do this if no other filters are applied (driver_id, route_id, pickup_day)
            if (($requestedDate === $today && (!$status || $status === 'unpicked')) || ($week && $status === 'unpicked')) {
                // Skip unpicked logic if other filters are applied
                if ($request->has('driver_id') || $request->has('route_id') || $request->has('pickup_day')) {
                    // Continue to regular pickup table query
                } else {
                    \Log::info('Getting unpicked clients...');
                    $unpickedClients = $week ? $this->getWeekUnpickedClients() : $this->getTodaysUnpickedClients();
                
                if (!$status) {
                    // Get picked from pickup table for today or week
                    $pickedQuery = Pickup::with(['client', 'route', 'driver'])
                        ->where('pickup_status', 'picked');
                    
                    if ($week) {
                        $pickedQuery->whereBetween('pickup_date', [$weekStart, $weekEnd]);
                    } else {
                        $pickedQuery->where('pickup_date', $today);
                    }
                    
                    $pickedToday = $pickedQuery->get();
                    
                    $response = [
                        'status' => true,
                        'data' => [
                            'picked' => $pickedToday,
                            'unpicked' => $unpickedClients,
                            'date' => $today
                        ]
                    ];
                    
                    \Log::info('📦 Get pickups response (picked/unpicked):', $response);
                    return response()->json($response, 200);
                    } else {
                        $response = [
                            'status' => true,
                            'data' => [
                                'pickups' => $unpickedClients,
                                'date' => $today
                            ]
                        ];
                        
                        \Log::info('📦 Get pickups response (unpicked only):', $response);
                        return response()->json($response, 200);
                    }
                }
            }

            // For other dates or specific status, query pickup table
            $query = Pickup::with(['client', 'route', 'driver']);

            if ($startDate && $endDate) {
                $query->whereBetween('pickup_date', [$startDate, $endDate]);
            } elseif ($requestedDate) {
                $query->where('pickup_date', $requestedDate);
            }

            if ($status) {
                $query->where('pickup_status', $status);
            }
            
            // Filter by driver
            if ($request->has('driver_id')) {
                if ($request->driver_id === 'unassigned') {
                    $query->whereNull('driver_id');
                } elseif (!empty($request->driver_id)) {
                    $query->where('driver_id', $request->driver_id);
                }
            }
            
            // Filter by route
            if ($request->has('route_id') && !empty($request->route_id)) {
                $query->where('route_id', $request->route_id);
            }
            
            // Filter by pickup day (check day of week from pickup_date)
            if ($request->has('pickup_day') && !empty($request->pickup_day)) {
                $dayName = ucfirst(strtolower($request->pickup_day));
                \Log::info('Filtering by pickup day:', ['requested' => $request->pickup_day, 'dayName' => $dayName]);
                $query->whereRaw('DAYNAME(pickup_date) = ?', [$dayName]);
            }
            
            // Filter by client name
            if ($request->has('client_name') && !empty($request->client_name)) {
                $clientName = $request->client_name;
                \Log::info('Filtering by client name:', ['client_name' => $clientName]);
                $query->whereHas('client', function($q) use ($clientName) {
                    $q->where('name', 'LIKE', '%' . $clientName . '%');
                });
            }
            
            \Log::info('Applied filters:', [
                'driver_id' => $request->driver_id,
                'route_id' => $request->route_id,
                'pickup_day' => $request->pickup_day,
                'client_name' => $request->client_name,
                'status' => $status
            ]);

            $pickups = $query->orderBy('pickup_date', 'desc')->get();

            \Log::info('Pickups found:', ['count' => $pickups->count()]);

            return response()->json([
                'status' => true,
                'data' => [
                    'pickups' => $pickups,
                    'filters' => [
                        'date' => $requestedDate,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => $status
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get pickups failed:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Failed to get pickups',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getClientsToPickup(Request $request)
    {
        \Log::info('=== GET CLIENTS TO PICKUP START ===');
        
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        try {
            $today = Carbon::now();
            $requestedDate = $request->date ? Carbon::parse($request->date) : $today;
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : null;
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : null;
            
            \Log::info('Client pickup query:', [
                'requested_date' => $requestedDate->toDateString(),
                'start_date' => $startDate?->toDateString(),
                'end_date' => $endDate?->toDateString()
            ]);

            if ($startDate && $endDate) {
                // Date range query
                $clients = [];
                $currentDate = $startDate->copy();
                
                while ($currentDate->lte($endDate)) {
                    $dayClients = $this->getClientsForDate($currentDate);
                    $clients[$currentDate->toDateString()] = $dayClients;
                    $currentDate->addDay();
                }
                
                return response()->json([
                    'status' => true,
                    'data' => [
                        'clients_by_date' => $clients,
                        'date_range' => [
                            'start' => $startDate->toDateString(),
                            'end' => $endDate->toDateString()
                        ]
                    ]
                ], 200);
            } else {
                // Single date query
                $clients = $this->getClientsForDate($requestedDate);
                
                return response()->json([
                    'status' => true,
                    'data' => [
                        'clients' => $clients,
                        'date' => $requestedDate->toDateString(),
                        'day_name' => $requestedDate->format('l')
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            \Log::error('Get clients to pickup failed:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Failed to get clients',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getTodaysUnpickedClients()
    {
        $today = Carbon::now();
        $todayName = strtolower($today->format('l'));
        
        // Get clients whose pickup day is today and service has started
        $clients = Client::with(['user', 'route'])
            ->whereNotNull('pickUpDay')
            ->whereNotNull('serviceStartDate')
            ->whereRaw('LOWER(pickUpDay) = ?', [$todayName])
            ->whereDate('serviceStartDate', '<=', $today)
            ->get();

        // Filter out clients who are already picked this week
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();
        
        $pickedClientIds = Pickup::where('pickup_status', 'picked')
            ->whereBetween('pickup_date', [$weekStart, $weekEnd])
            ->pluck('client_id')
            ->toArray();

        $unpickedClients = $clients->filter(function($client) use ($pickedClientIds) {
            return !in_array($client->user_id, $pickedClientIds);
        })->values();

        return $unpickedClients;
    }

    private function getWeekUnpickedClients()
    {
        $today = Carbon::now();
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();
        
        // Get all clients who should be picked this week
        $weekClients = collect();
        $currentDate = $weekStart->copy();
        
        while ($currentDate->lte($weekEnd)) {
            $dayName = strtolower($currentDate->format('l'));
            
            $dayClients = Client::with(['user', 'route'])
                ->whereNotNull('pickUpDay')
                ->whereNotNull('serviceStartDate')
                ->whereRaw('LOWER(pickUpDay) = ?', [$dayName])
                ->whereDate('serviceStartDate', '<=', $currentDate)
                ->get();
                
            $weekClients = $weekClients->merge($dayClients);
            $currentDate->addDay();
        }
        
        // Remove duplicates (same client might appear multiple times)
        $weekClients = $weekClients->unique('id');
        
        // Filter out clients who are already picked this week
        $pickedClientIds = Pickup::where('pickup_status', 'picked')
            ->whereBetween('pickup_date', [$weekStart, $weekEnd])
            ->pluck('client_id')
            ->toArray();

        $unpickedClients = $weekClients->filter(function($client) use ($pickedClientIds) {
            return !in_array($client->user_id, $pickedClientIds);
        })->values();

        return $unpickedClients;
    }

    private function getClientsForDate(Carbon $date)
    {
        $dayName = strtolower($date->format('l'));
        
        // Get clients whose pickup day matches the requested date
        $clients = Client::with(['user', 'route'])
            ->whereNotNull('pickUpDay')
            ->whereNotNull('serviceStartDate')
            ->whereRaw('LOWER(pickUpDay) = ?', [$dayName])
            ->whereDate('serviceStartDate', '<=', $date)
            ->get();

        return $clients;
    }

    public function getClientPickups(Request $request, $clientId)
    {
        \Log::info('=== GET CLIENT PICKUPS START ===');
        \Log::info('Client ID:', ['client_id' => $clientId]);
        
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:picked,unpicked,skipped'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }

        try {
            // Verify client exists
            $client = \App\Models\User::where('id', $clientId)
                ->where('role', 'client')
                ->first();

            if (!$client) {
                return response()->json([
                    'status' => false,
                    'error' => 'Client not found',
                    'message' => 'Client not found'
                ], 404);
            }

            \Log::info('Client found:', ['name' => $client->name, 'email' => $client->email]);

            // Build query
            $query = Pickup::with(['route', 'driver'])
                ->where('client_id', $clientId);

            // Apply filters
            if ($request->date) {
                $query->where('pickup_date', $request->date);
            }

            if ($request->start_date && $request->end_date) {
                $query->whereBetween('pickup_date', [$request->start_date, $request->end_date]);
            }

            if ($request->status) {
                $query->where('pickup_status', $request->status);
            }

            $pickups = $query->orderBy('pickup_date', 'desc')->get();

            \Log::info('Client pickups found:', ['count' => $pickups->count()]);

            return response()->json([
                'status' => true,
                'data' => [
                    'client' => [
                        'id' => $client->id,
                        'name' => $client->name,
                        'email' => $client->email
                    ],
                    'pickups' => $pickups,
                    'filters' => [
                        'date' => $request->date,
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'status' => $request->status
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Get client pickups failed:', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Failed to get client pickups',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function activateRoute(Request $request)
    {
        \Log::info('=== DRIVER ROUTE ACTIVATION START ===');
        \Log::info('Request data:', $request->all());
        \Log::info('Driver ID:', ['driver_id' => $request->user()->id]);

        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:routes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid route ID'
            ], 401);
        }

        try {
            $driverId = $request->user()->id;
            $routeId = $request->route_id;

            // Check if driver has existing record
            $existingRecord = \App\Models\DriverRoute::where('driver_id', $driverId)->first();

            if ($existingRecord) {
                // Check if already active on this route
                if ($existingRecord->route_id == $routeId && $existingRecord->is_active) {
                    return response()->json([
                        'status' => false,
                        'error' => 'Already active',
                        'message' => 'Driver is already active on this route'
                    ], 400);
                }

                // Update existing record to new route
                $existingRecord->update([
                    'route_id' => $routeId,
                    'is_active' => true,
                    'activated_at' => now()
                ]);

                \Log::info('Updated driver route record:', [
                    'driver_id' => $driverId,
                    'new_route_id' => $routeId,
                    'record_id' => $existingRecord->id
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Successfully activated on route',
                    'data' => ['activation' => $existingRecord->fresh()->load('route')]
                ], 200);
            } else {
                // Create new record
                $activation = \App\Models\DriverRoute::create([
                    'driver_id' => $driverId,
                    'route_id' => $routeId,
                    'is_active' => true,
                    'activated_at' => now()
                ]);

                \Log::info('Created new driver route record:', [
                    'driver_id' => $driverId,
                    'route_id' => $routeId,
                    'activation_id' => $activation->id
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Successfully activated on route',
                    'data' => ['activation' => $activation->load('route')]
                ], 200);
            }

        } catch (\Exception $e) {
            \Log::error('Route activation failed:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Activation failed',
                'message' => 'Failed to activate on route: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deactivateRoute(Request $request)
    {
        \Log::info('=== DRIVER ROUTE DEACTIVATION START ===');
        
        try {
            $driverId = $request->user()->id;

            $driverRecord = \App\Models\DriverRoute::where('driver_id', $driverId)
                ->where('is_active', true)
                ->first();

            if (!$driverRecord) {
                return response()->json([
                    'status' => false,
                    'error' => 'Not active',
                    'message' => 'Driver is not active on any route'
                ], 400);
            }

            $driverRecord->update([
                'is_active' => false,
                'deactivated_at' => now()
            ]);

            \Log::info('Driver deactivated from route:', [
                'driver_id' => $driverId,
                'route_id' => $driverRecord->route_id
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Successfully deactivated from route'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Route deactivation failed:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Deactivation failed',
                'message' => 'Failed to deactivate from route'
            ], 500);
        }
    }

    public function getActiveRoutes(Request $request)
    {
        try {
            $driverId = $request->user()->id;

            $activeRoute = \App\Models\DriverRoute::with('route')
                ->where('driver_id', $driverId)
                ->where('is_active', true)
                ->first();

            return response()->json([
                'status' => true,
                'data' => [
                    'route' => $activeRoute ? [
                        'id' => $activeRoute->route->id,
                        'name' => $activeRoute->route->name,
                        'path' => $activeRoute->route->path ?? null
                    ] : null
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Failed to get active routes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getTodayPickupsSummary(Request $request)
    {
        $organizationId = $request->user()->id;
        $today = \Carbon\Carbon::now()->toDateString();
        
        // Get today's pickups with route information
        $pickups = \App\Models\Pickup::with(['client', 'route'])
            ->whereDate('pickup_date', $today)
            ->whereHas('client', function($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            })
            ->get();
            
        $total = $pickups->count();
        $completed = $pickups->where('pickup_status', 'picked')->count();
        $pending = $total - $completed;
        
        // Group by routes
        $routesSummary = [];
        $pickupsByRoute = $pickups->groupBy('route_id');
        
        foreach ($pickupsByRoute as $routeId => $routePickups) {
            $route = $routePickups->first()->route;
            $routesSummary[] = [
                'route_id' => $routeId,
                'route_name' => $route ? $route->name : 'Unknown Route',
                'total' => $routePickups->count(),
                'completed' => $routePickups->where('pickup_status', 'picked')->count(),
                'pending' => $routePickups->where('pickup_status', '!=', 'picked')->count()
            ];
        }
        
        return response()->json([
            'status' => true,
            'data' => [
                'total' => $total,
                'completed' => $completed,
                'pending' => $pending,
                'routes' => $routesSummary
            ]
        ], 200);
    }

    public function createPickup(Request $request)
    {
        \Log::info('=== CREATE PICKUP START ===');
        \Log::info('Request data:', $request->all());
        
        $organizationId = $request->user()->id;
        
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'driver_id' => 'nullable|exists:users,id',
            'pickup_date' => 'required|date',
            'status' => 'required|in:scheduled,picked'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation failed',
                'details' => collect($validator->errors())->map(function($messages, $field) {
                    return ['field' => $field, 'message' => $messages[0]];
                })->values()
            ], 401);
        }
        
        try {
            // Get client details to find route
            $client = \App\Models\User::where('id', $request->client_id)
                ->where('role', 'client')
                ->first();
                
            if (!$client) {
                return response()->json([
                    'status' => false,
                    'error' => 'Client not found',
                    'message' => 'Client not found or invalid'
                ], 404);
            }
            
            $clientRecord = \App\Models\Client::where('user_id', $client->id)->first();
            
            $pickupData = [
                'client_id' => $request->client_id,
                'route_id' => $clientRecord->route_id ?? null,
                'pickup_status' => $request->status,
                'pickup_date' => $request->pickup_date
            ];
            
            if ($request->driver_id) {
                $pickupData['driver_id'] = $request->driver_id;
            }
            
            if ($request->status === 'picked') {
                $pickupData['picked_at'] = now();
            }
            
            $pickup = \App\Models\Pickup::create($pickupData);
            
            $response = [
                'status' => true,
                'message' => 'Pickup created successfully',
                'data' => ['pickup' => $pickup->load(['client', 'route', 'driver'])]
            ];
            
            \Log::info('📦 Create pickup response:', $response);
            return response()->json($response, 200);
            
        } catch (\Exception $e) {
            \Log::error('Create pickup failed:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => false,
                'error' => 'Failed to create pickup',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}