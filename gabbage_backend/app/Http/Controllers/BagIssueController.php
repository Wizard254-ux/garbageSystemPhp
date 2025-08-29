<?php

namespace App\Http\Controllers;

use App\Models\BagIssue;
use App\Models\DriverBagsAllocation;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class BagIssueController extends Controller
{
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|integer',
            'contact' => 'required|email',
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
            ], 422);
        }

        $driverId = $request->user()->id;
        
        // Check if client belongs to driver's organization using clients table
        $client = \App\Models\Client::where('id', $request->client_id)
            ->where('organization_id', $request->user()->organization_id)
            ->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'error' => 'Invalid client',
                'message' => 'Client not found or does not belong to your organization'
            ], 404);
        }

        // Check driver's bag allocation (only active status = 1)
        $allocation = DriverBagsAllocation::where('driver_id', $driverId)
            ->where('status', 1)
            ->first();

        if (!$allocation || $allocation->available_bags < $request->number_of_bags) {
            return response()->json([
                'status' => false,
                'error' => 'Insufficient bags',
                'message' => 'You do not have enough bags to issue'
            ], 400);
        }

        // Generate OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Create bag issue record
        $bagIssue = BagIssue::create([
            'driver_id' => $driverId,
            'client_id' => $client->user_id, // Use the user_id from client record
            'client_email' => $request->contact,
            'number_of_bags_issued' => $request->number_of_bags,
            'otp_code' => $otpCode,
            'otp_expires_at' => now()->addMinutes(10),
            'is_verified' => false
        ]);

        // Send OTP via email
        try {
            Mail::raw("Your OTP for bag collection is: {$otpCode}. This code expires in 10 minutes.", function($message) use ($request) {
                $message->to($request->contact)
                        ->subject('Bag Collection OTP');
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send OTP email: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to client email successfully',
            'data' => [
                'bag_issue_id' => $bagIssue->id,
                'expires_at' => $bagIssue->otp_expires_at
            ]
        ], 200);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bag_issue_id' => 'required|exists:bag_issues,id',
            'otp_code' => 'required|string|size:6'
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

        $bagIssue = BagIssue::where('id', $request->bag_issue_id)
            ->where('driver_id', $request->user()->id)
            ->first();

        if (!$bagIssue) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Bag issue record not found'
            ], 404);
        }

        // Check if already verified
        if ($bagIssue->is_verified) {
            return response()->json([
                'status' => false,
                'error' => 'Already verified',
                'message' => 'This bag issue has already been verified'
            ], 400);
        }

        // Check OTP expiration
        if (now()->gt($bagIssue->otp_expires_at)) {
            return response()->json([
                'status' => false,
                'error' => 'OTP expired',
                'message' => 'The OTP has expired. Please request a new one.'
            ], 400);
        }

        // Verify OTP
        if ($bagIssue->otp_code !== $request->otp_code) {
            return response()->json([
                'status' => false,
                'error' => 'Invalid OTP',
                'message' => 'The provided OTP is incorrect'
            ], 400);
        }

        // Process the bag issue
        DB::transaction(function () use ($bagIssue, $request) {
            // Update driver allocation (only active status = 1)
            $allocation = DriverBagsAllocation::where('driver_id', $request->user()->id)
                ->where('status', 1)
                ->first();
            $allocation->decrement('available_bags', $bagIssue->number_of_bags_issued);
            $allocation->increment('used_bags', $bagIssue->number_of_bags_issued);

            // Mark as verified and set issued time
            $bagIssue->update([
                'is_verified' => true,
                'issued_at' => now()
            ]);

            // Log the activity
            $client = User::find($bagIssue->client_id);
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'bags_issued',
                'description' => "{$request->user()->name} issued {$bagIssue->number_of_bags_issued} bags to client {$client->name}",
                'reason' => null,
                'data' => [
                    'bags_issued' => $bagIssue->number_of_bags_issued,
                    'client_id' => $bagIssue->client_id,
                    'client_name' => $client->name,
                    'client_email' => $bagIssue->client_email,
                    'driver_remaining' => $allocation->fresh()->available_bags
                ]
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Bags issued successfully',
            'data' => [
                'bag_issue' => $bagIssue->fresh()->load(['driver:id,name', 'client:id,name'])
            ]
        ], 200);
    }

    public function index(Request $request)
    {
        \Log::info('=== BAG ISSUES SEARCH START ===');
        \Log::info('Request params:', $request->all());
        
        $organizationId = $request->user()->id;
        \Log::info('Organization ID:', ['org_id' => $organizationId]);
        
        $query = BagIssue::whereHas('driver', function($driverQuery) use ($organizationId) {
                $driverQuery->where('organization_id', $organizationId);
            })
            ->with(['driver:id,name', 'client:id,name']);
        
        // Add search functionality
        if ($request->has('search') && !empty(trim($request->search))) {
            $searchTerm = trim($request->search);
            \Log::info('APPLYING SEARCH FILTER with term:', ['search' => $searchTerm]);
            
            $query->where(function($q) use ($searchTerm) {
                $q->whereHas('driver', function($driverQuery) use ($searchTerm) {
                      $driverQuery->where('name', 'like', '%' . $searchTerm . '%');
                  })
                  ->orWhereHas('client', function($clientQuery) use ($searchTerm) {
                      $clientQuery->where('name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }
        
        // Add status filter
        if ($request->has('status') && !empty(trim($request->status))) {
            $statusFilter = trim($request->status);
            \Log::info('APPLYING STATUS FILTER:', ['status' => $statusFilter]);
            
            if ($statusFilter === 'verified') {
                $query->where('is_verified', true);
            } elseif ($statusFilter === 'pending') {
                $query->where('is_verified', false);
            }
        }
        
        $bagIssues = $query->orderBy('created_at', 'desc')->get();
        \Log::info('Bag issues found:', ['count' => $bagIssues->count()]);
        \Log::info('=== BAG ISSUES SEARCH END ===');

        return response()->json([
            'status' => true,
            'data' => ['bag_issues' => $bagIssues]
        ], 200);
    }
}