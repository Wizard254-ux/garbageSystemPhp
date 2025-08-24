<?php

namespace App\Http\Controllers;

use App\Models\Bag;
use App\Models\BagIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BagIssueController extends Controller
{
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bag_id' => 'required|exists:bags,id',
            'client_email' => 'required|email',
            'number_of_bags_issued' => 'required|integer|min:1'
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

        // Verify bag belongs to driver's organization
        $bag = Bag::find($request->bag_id);
        $driver = $request->user();
        
        if ($bag->organization_id !== $driver->organization_id) {
            return response()->json([
                'status' => false,
                'error' => 'Unauthorized',
                'message' => 'You cannot issue bags from this organization'
            ], 403);
        }

        // Check if bag has enough quantity
        $totalIssued = BagIssue::where('bag_id', $request->bag_id)
            ->where('is_verified', true)
            ->sum('number_of_bags_issued');
            
        if (($totalIssued + $request->number_of_bags_issued) > $bag->number_of_bags) {
            return response()->json([
                'status' => false,
                'error' => 'Insufficient bags',
                'message' => 'Not enough bags available for issuing'
            ], 400);
        }

        // Generate OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Create or update bag issue record
        $bagIssue = BagIssue::updateOrCreate(
            [
                'bag_id' => $request->bag_id,
                'client_email' => $request->client_email,
                'driver_id' => $driver->id,
                'is_verified' => false
            ],
            [
                'number_of_bags_issued' => $request->number_of_bags_issued,
                'otp_code' => $otpCode,
                'otp_expires_at' => now()->addMinutes(10)
            ]
        );

        // Send OTP via email (you'll need to create the mail class)
        try {
            Mail::raw("Your OTP for bag collection is: {$otpCode}. This code expires in 10 minutes.", function($message) use ($request) {
                $message->to($request->client_email)
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

        // Mark as verified and set issued time
        $bagIssue->update([
            'is_verified' => true,
            'issued_at' => now()
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Bags issued successfully',
            'data' => [
                'bag_issue' => $bagIssue->load(['bag', 'driver:id,name'])
            ]
        ], 200);
    }

    public function index(Request $request)
    {
        $organizationId = $request->user()->id;
        
        $bagIssues = BagIssue::whereHas('bag', function($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->with(['bag', 'driver:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => ['bag_issues' => $bagIssues]
        ], 200);
    }
}