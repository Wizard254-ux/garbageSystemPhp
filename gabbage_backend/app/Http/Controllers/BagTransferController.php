<?php

namespace App\Http\Controllers;

use App\Models\BagTransfer;
use App\Models\DriverBagsAllocation;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BagTransferController extends Controller
{
    public function initiateBagTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_driver_id' => 'required|exists:users,id',
            'number_of_bags' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
            'contact' => 'nullable|email'
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

        $fromDriverId = $request->user()->id;
        $organizationId = $request->user()->organization_id;

        // Check if receiving driver belongs to same organization
        $toDriver = User::where('id', $request->to_driver_id)
            ->where('organization_id', $organizationId)
            ->where('role', 'driver')
            ->first();

        if (!$toDriver) {
            return response()->json([
                'status' => false,
                'error' => 'Invalid driver',
                'message' => 'Receiving driver not found or does not belong to your organization'
            ], 404);
        }

        // Check if sender has enough bags
        $senderAllocation = DriverBagsAllocation::where('driver_id', $fromDriverId)->first();
        if (!$senderAllocation || $senderAllocation->available_bags < $request->number_of_bags) {
            return response()->json([
                'status' => false,
                'error' => 'Insufficient bags',
                'message' => 'Not enough bags available for transfer'
            ], 400);
        }

        // Generate OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpExpiresAt = Carbon::now()->addMinutes(15);

        $transfer = BagTransfer::create([
            'from_driver_id' => $fromDriverId,
            'to_driver_id' => $request->to_driver_id,
            'organization_id' => $organizationId,
            'number_of_bags' => $request->number_of_bags,
            'otp_code' => $otpCode,
            'otp_expires_at' => $otpExpiresAt,
            'status' => 'pending',
            'notes' => $request->notes
        ]);

        // Send OTP to custom email or receiving driver's email
        $emailAddress = $request->contact ?: $toDriver->email;
        try {
            Mail::raw("Your bag transfer OTP is: {$otpCode}. This code expires in 15 minutes. Transfer from {$request->user()->name} to {$toDriver->name} for {$request->number_of_bags} bags.", function ($message) use ($emailAddress) {
                $message->to($emailAddress)
                    ->subject('Bag Transfer OTP - GreenLife');
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send bag transfer OTP email', [
                'error' => $e->getMessage(),
                'email' => $emailAddress,
                'transfer_id' => $transfer->id ?? null
            ]);
            return response()->json([
                'status' => false,
                'error' => 'Email failed',
                'message' => 'Failed to send OTP email: ' . $e->getMessage()
            ], 500);
        }

        ActivityLog::create([
            'user_id' => $fromDriverId,
            'action' => 'bag_transfer_initiated',
            'description' => "Initiated transfer of {$request->number_of_bags} bags to {$toDriver->name}",
            'data' => [
                'transfer_id' => $transfer->id,
                'to_driver' => $toDriver->name,
                'bags_count' => $request->number_of_bags
            ]
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Bag transfer initiated. OTP sent to receiving driver.',
            'data' => [
                'transfer_id' => $transfer->id,
                'to_driver' => $toDriver->name,
                'expires_at' => $otpExpiresAt
            ]
        ], 200);
    }

    public function completeBagTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transfer_id' => 'required|exists:bag_transfers,id',
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

        $transfer = BagTransfer::with(['fromDriver', 'toDriver'])->find($request->transfer_id);

        // Allow both the sending driver and receiving driver to complete the transfer
        if ($transfer->to_driver_id !== $request->user()->id && $transfer->from_driver_id !== $request->user()->id) {
            return response()->json([
                'status' => false,
                'error' => 'Unauthorized',
                'message' => 'You are not authorized to complete this transfer'
            ], 403);
        }

        if ($transfer->status !== 'pending') {
            return response()->json([
                'status' => false,
                'error' => 'Invalid transfer',
                'message' => 'Transfer is not in pending status'
            ], 400);
        }

        if ($transfer->otp_expires_at < Carbon::now()) {
            return response()->json([
                'status' => false,
                'error' => 'OTP expired',
                'message' => 'The OTP has expired'
            ], 400);
        }

        if ($transfer->otp_code !== $request->otp_code) {
            return response()->json([
                'status' => false,
                'error' => 'Invalid OTP',
                'message' => 'The OTP code is incorrect'
            ], 400);
        }

        DB::transaction(function () use ($transfer) {
            // Update sender allocation (only active status = 1)
            $senderAllocation = DriverBagsAllocation::where('driver_id', $transfer->from_driver_id)
                ->where('status', 1)
                ->first();
            if ($senderAllocation) {
                $senderAllocation->decrement('available_bags', $transfer->number_of_bags);
                $senderAllocation->decrement('allocated_bags', $transfer->number_of_bags);
            }

            // Update receiver allocation (only active status = 1)
            $receiverAllocation = DriverBagsAllocation::where('organization_id', $transfer->organization_id)
                ->where('driver_id', $transfer->to_driver_id)
                ->where('status', 1)
                ->first();
                
            if ($receiverAllocation) {
                $receiverAllocation->increment('available_bags', $transfer->number_of_bags);
                $receiverAllocation->increment('allocated_bags', $transfer->number_of_bags);
            } else {
                // Create new active allocation for receiver
                DriverBagsAllocation::create([
                    'organization_id' => $transfer->organization_id,
                    'driver_id' => $transfer->to_driver_id,
                    'allocated_bags' => $transfer->number_of_bags,
                    'used_bags' => 0,
                    'available_bags' => $transfer->number_of_bags,
                    'bags_from_previous' => 0,
                    'status' => 1
                ]);
            }

            // Update transfer status
            $transfer->update([
                'status' => 'completed',
                'completed_at' => Carbon::now()
            ]);

            ActivityLog::create([
                'user_id' => $transfer->to_driver_id,
                'action' => 'bag_transfer_completed',
                'description' => "Received {$transfer->number_of_bags} bags from {$transfer->fromDriver->name}",
                'data' => [
                    'transfer_id' => $transfer->id,
                    'from_driver' => $transfer->fromDriver->name,
                    'bags_count' => $transfer->number_of_bags
                ]
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Bag transfer completed successfully',
            'data' => [
                'transfer' => $transfer->fresh(),
                'new_bag_count' => DriverBagsAllocation::where('driver_id', $transfer->to_driver_id)->first()->available_bags
            ]
        ], 200);
    }

    public function getTransferHistory(Request $request)
    {
        $driverId = $request->user()->id;
        
        $transfers = BagTransfer::with(['fromDriver:id,name', 'toDriver:id,name'])
            ->where(function($query) use ($driverId) {
                $query->where('from_driver_id', $driverId)
                      ->orWhere('to_driver_id', $driverId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $transfers
        ], 200);
    }

    public function getOrganizationTransfers(Request $request)
    {
        $organizationId = $request->user()->id;
        
        $transfers = BagTransfer::with(['fromDriver:id,name', 'toDriver:id,name'])
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'status' => true,
            'data' => $transfers->items()
        ], 200);
    }
}