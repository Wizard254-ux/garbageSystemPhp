<?php

namespace App\Http\Controllers;

use App\Models\Bag;
use App\Models\DriverBagsAllocation;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BagController extends Controller
{
    // Organization adds bags to their inventory
    public function addBags(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number_of_bags' => 'required|integer|min:1'
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

        $organizationId = $request->user()->id;
        
        $bag = Bag::firstOrCreate(
            ['organization_id' => $organizationId],
            ['total_bags' => 0, 'allocated_bags' => 0, 'available_bags' => 0]
        );

        $bag->increment('total_bags', $request->number_of_bags);
        $bag->increment('available_bags', $request->number_of_bags);

        // Log the activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'bags_added',
            'description' => "Added {$request->number_of_bags} bags to inventory",
            'reason' => null,
            'data' => [
                'bags_added' => $request->number_of_bags,
                'new_total' => $bag->fresh()->total_bags,
                'new_available' => $bag->fresh()->available_bags
            ]
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Bags added successfully',
            'data' => ['bags' => $bag->fresh()]
        ], 200);
    }

    // Organization removes bags (damaged/spoilt)
    public function removeBags(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'number_of_bags' => 'required|integer|min:1',
            'reason' => 'required|string|max:255'
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

        $organizationId = $request->user()->id;
        $bag = Bag::where('organization_id', $organizationId)->first();

        if (!$bag || $bag->available_bags < $request->number_of_bags) {
            return response()->json([
                'status' => false,
                'error' => 'Insufficient bags',
                'message' => 'Not enough available bags to remove'
            ], 400);
        }

        $bag->decrement('total_bags', $request->number_of_bags);
        $bag->decrement('available_bags', $request->number_of_bags);

        // Log the activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'bags_removed',
            'description' => "Removed {$request->number_of_bags} bags from inventory",
            'reason' => $request->reason,
            'data' => [
                'bags_removed' => $request->number_of_bags,
                'remaining_total' => $bag->fresh()->total_bags,
                'remaining_available' => $bag->fresh()->available_bags
            ]
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Bags removed successfully',
            'data' => ['bags' => $bag->fresh(), 'reason' => $request->reason]
        ], 200);
    }

    // Organization allocates bags to driver
    public function allocateToDriver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:users,id',
            'number_of_bags' => 'required|integer|min:1'
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

        $organizationId = $request->user()->id;
        
        // Check if driver belongs to organization
        $driver = User::where('id', $request->driver_id)
            ->where('organization_id', $organizationId)
            ->where('role', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => false,
                'error' => 'Invalid driver',
                'message' => 'Driver not found or does not belong to your organization'
            ], 404);
        }

        $bag = Bag::where('organization_id', $organizationId)->first();

        if (!$bag || $bag->available_bags < $request->number_of_bags) {
            return response()->json([
                'status' => false,
                'error' => 'Insufficient bags',
                'message' => 'Not enough available bags to allocate'
            ], 400);
        }

        DB::transaction(function () use ($bag, $request, $organizationId, $driver) {
            // Update organization bags
            $bag->decrement('available_bags', $request->number_of_bags);
            $bag->increment('allocated_bags', $request->number_of_bags);

            // Update driver allocation
            $allocation = DriverBagsAllocation::firstOrCreate(
                ['organization_id' => $organizationId, 'driver_id' => $request->driver_id],
                ['allocated_bags' => 0, 'used_bags' => 0, 'available_bags' => 0]
            );

            $allocation->increment('allocated_bags', $request->number_of_bags);
            $allocation->increment('available_bags', $request->number_of_bags);

            // Log the activity
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'bags_allocated',
                'description' => "Allocated {$request->number_of_bags} bags to driver {$driver->name}",
                'reason' => null,
                'data' => [
                    'bags_allocated' => $request->number_of_bags,
                    'driver_id' => $request->driver_id,
                    'driver_name' => $driver->name,
                    'organization_available' => $bag->fresh()->available_bags,
                    'driver_total' => $allocation->fresh()->available_bags
                ]
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Bags allocated to driver successfully',
            'data' => [
                'organization_bags' => $bag->fresh(),
                'driver_allocation' => DriverBagsAllocation::where('organization_id', $organizationId)
                    ->where('driver_id', $request->driver_id)
                    ->with('driver:id,name')
                    ->first()
            ]
        ], 200);
    }

    // Organization processes bag returns from drivers
    public function processBagReturn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:users,id',
            'number_of_bags' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255'
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

        $organizationId = $request->user()->id;
        
        // Check if driver belongs to organization
        $driver = User::where('id', $request->driver_id)
            ->where('organization_id', $organizationId)
            ->where('role', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => false,
                'error' => 'Invalid driver',
                'message' => 'Driver not found or does not belong to your organization'
            ], 404);
        }

        $allocation = DriverBagsAllocation::where('driver_id', $request->driver_id)->first();

        if (!$allocation || $allocation->available_bags < $request->number_of_bags) {
            return response()->json([
                'status' => false,
                'error' => 'Insufficient bags',
                'message' => 'Driver does not have enough bags to return'
            ], 400);
        }

        DB::transaction(function () use ($allocation, $request, $organizationId, $driver) {
            // Update driver allocation
            $allocation->decrement('allocated_bags', $request->number_of_bags);
            $allocation->decrement('available_bags', $request->number_of_bags);

            // Update organization bags
            $bag = Bag::where('organization_id', $organizationId)->first();
            $bag->increment('available_bags', $request->number_of_bags);
            $bag->decrement('allocated_bags', $request->number_of_bags);

            // Log the activity
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'bags_return_processed',
                'description' => "Processed return of {$request->number_of_bags} bags from driver {$driver->name}",
                'reason' => $request->reason,
                'data' => [
                    'bags_returned' => $request->number_of_bags,
                    'driver_id' => $request->driver_id,
                    'driver_name' => $driver->name,
                    'driver_remaining' => $allocation->fresh()->available_bags,
                    'organization_available' => $bag->fresh()->available_bags
                ]
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Bag return processed successfully',
            'data' => [
                'driver_allocation' => $allocation->fresh(),
                'organization_bags' => Bag::where('organization_id', $organizationId)->first()
            ]
        ], 200);
    }

    // Get driver bag statistics
    public function getDriverBagStats(Request $request)
    {
        $driverId = $request->user()->id;
        
        $allocation = DriverBagsAllocation::where('driver_id', $driverId)->first();

        return response()->json([
            'status' => true,
            'data' => [
                'bags' => $allocation ?? [
                    'allocated_bags' => 0,
                    'used_bags' => 0,
                    'available_bags' => 0
                ]
            ]
        ], 200);
    }

    // Get organization bags overview
    public function getOrganizationBags(Request $request)
    {
        $organizationId = $request->user()->id;
        
        $bags = Bag::where('organization_id', $organizationId)->first();
        $driverAllocations = DriverBagsAllocation::where('organization_id', $organizationId)
            ->with('driver:id,name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'organization_bags' => $bags,
                'driver_allocations' => $driverAllocations
            ]
        ], 200);
    }

    // Get driver bags (enhanced)
    public function getDriverBags(Request $request)
    {
        $driverId = $request->user()->id;
        
        $allocation = DriverBagsAllocation::where('driver_id', $driverId)->first();

        return response()->json([
            'status' => true,
            'data' => [
                'driver_bags' => $allocation ?? [
                    'allocated_bags' => 0,
                    'used_bags' => 0,
                    'available_bags' => 0
                ]
            ]
        ], 200);
    }
}