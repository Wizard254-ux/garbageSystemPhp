<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = $request->user()->id;
        $payments = Payment::where('organization_id', $organizationId)
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => ['payments' => $payments]
        ], 200);
    }

    public function mpesaCallback(Request $request)
    {
        \Log::info('=== MPESA CALLBACK START ===');
        \Log::info('Callback data:', $request->all());

        try {
            DB::beginTransaction();

            // Find client by account number
            $client = Client::where('accountNumber', $request->BillRefNumber)->first();
            
            if (!$client) {
                \Log::error('Client not found for account number:', ['account' => $request->BillRefNumber]);
                return response()->json(['status' => 'failed', 'message' => 'Invalid account number'], 400);
            }

            \Log::info('Client found:', ['client_id' => $client->id, 'account' => $client->accountNumber]);

            // Create payment record
            $payment = Payment::create([
                'trans_id' => $request->TransID,
                'account_number' => $request->BillRefNumber,
                'client_id' => $client->user_id,
                'organization_id' => $client->organization_id,
                'amount' => $request->TransAmount,
                'phone_number' => $request->MSISDN,
                'first_name' => $request->FirstName ?? '',
                'last_name' => $request->LastName ?? '',
                'remaining_amount' => $request->TransAmount,
                'trans_time' => \Carbon\Carbon::createFromFormat('YmdHis', $request->TransTime)
            ]);

            \Log::info('Payment created:', ['payment_id' => $payment->id, 'amount' => $payment->amount]);

            // Process payment allocation
            $this->allocatePaymentToInvoices($payment);

            // Send payment received email
            try {
                \Log::info('Sending payment received email to client:', ['email' => $client->user->email]);
                \Mail::to($client->user->email)->send(new \App\Mail\PaymentReceived($payment->fresh()->load(['client', 'organization'])));
                \Log::info('Payment received email sent successfully');
            } catch (\Exception $e) {
                \Log::error('Failed to send payment received email:', [
                    'error' => $e->getMessage(),
                    'client_email' => $client->user->email
                ]);
            }

            DB::commit();
            \Log::info('=== MPESA CALLBACK END - SUCCESS ===');

            return response()->json(['status' => 'success', 'message' => 'Payment processed']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment processing failed:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['status' => 'failed', 'message' => 'Payment processing failed'], 500);
        }
    }

    private function allocatePaymentToInvoices(Payment $payment)
    {
        \Log::info('=== ALLOCATING PAYMENT TO INVOICES ===');
        
        $remainingAmount = $payment->remaining_amount;
        $processedInvoices = [];
        $allocatedAmount = 0;

        // Get unpaid/partially paid invoices ordered by oldest first
        $invoices = Invoice::where('client_id', $payment->client_id)
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->orderBy('created_at', 'asc')
            ->get();

        \Log::info('Found invoices to process:', ['count' => $invoices->count()]);

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) break;

            $amountDue = $invoice->amount - $invoice->paid_amount;
            $paymentAmount = min($remainingAmount, $amountDue);

            \Log::info('Processing invoice:', [
                'invoice_id' => $invoice->id,
                'amount_due' => $amountDue,
                'payment_amount' => $paymentAmount
            ]);

            // Update invoice
            $invoice->paid_amount += $paymentAmount;
            $invoice->payment_status = $invoice->paid_amount >= $invoice->amount ? 'fully_paid' : 'partially_paid';
            
            $paymentIds = $invoice->payment_ids ?? [];
            $paymentIds[] = $payment->id;
            $invoice->payment_ids = $paymentIds;
            
            $invoice->save();

            $processedInvoices[] = $invoice->id;
            $allocatedAmount += $paymentAmount;
            $remainingAmount -= $paymentAmount;

            \Log::info('Invoice updated:', [
                'invoice_id' => $invoice->id,
                'paid_amount' => $invoice->paid_amount,
                'status' => $invoice->payment_status
            ]);
        }

        // Update payment status
        $status = 'not_allocated';
        if ($allocatedAmount > 0) {
            $status = $remainingAmount > 0 ? 'partially_allocated' : 'fully_allocated';
        }

        $payment->update([
            'status' => $status,
            'invoices_processed' => $processedInvoices,
            'allocated_amount' => $allocatedAmount,
            'remaining_amount' => $remainingAmount
        ]);

        \Log::info('Payment allocation completed:', [
            'payment_id' => $payment->id,
            'status' => $status,
            'allocated_amount' => $allocatedAmount,
            'remaining_amount' => $remainingAmount,
            'invoices_processed' => $processedInvoices
        ]);
    }

    public function show(Request $request, $id)
    {
        $organizationId = $request->user()->id;
        $payment = Payment::where('organization_id', $organizationId)
            ->with('client')
            ->find($id);

        if (!$payment) {
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => ['payment' => $payment]
        ], 200);
    }

    public function getClientPayments(Request $request, $clientId)
    {
        $organizationId = $request->user()->id;
        
        $payments = Payment::where('client_id', $clientId)
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => ['payments' => $payments]
        ], 200);
    }

    public function createCashPayment(Request $request)
    {
        \Log::info('=== CASH PAYMENT CREATION START ===');
        \Log::info('Request data:', $request->all());
        
        $validator = \Validator::make($request->all(), [
            'client_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500'
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

        try {
            DB::beginTransaction();

            // Get client details
            $client = \App\Models\User::where('id', $request->client_id)
                ->where('role', 'client')
                ->first();

            if (!$client) {
                return response()->json([
                    'status' => false,
                    'error' => 'Client not found',
                    'message' => 'Client not found'
                ], 404);
            }

            // Get client record for account number
            $clientRecord = Client::where('user_id', $request->client_id)->first();
            
            // Generate transaction ID
            $transId = strtoupper($request->payment_method) . '_' . time() . '_' . rand(1000, 9999);
            
            // Create payment record
            $payment = Payment::create([
                'trans_id' => $transId,
                'payment_method' => $request->payment_method,
                'account_number' => $clientRecord->accountNumber ?? 'N/A',
                'client_id' => $request->client_id,
                'organization_id' => $request->user()->id,
                'amount' => $request->amount,
                'phone_number' => $client->phone,
                'first_name' => $client->name,
                'last_name' => '',
                'status' => 'not_allocated',
                'allocated_amount' => 0,
                'remaining_amount' => $request->amount,
                'invoices_processed' => [],
                'trans_time' => now()
            ]);

            \Log::info('Cash payment created:', [
                'payment_id' => $payment->id,
                'trans_id' => $payment->trans_id,
                'amount' => $payment->amount,
                'method' => $payment->payment_method
            ]);

            // Use the same allocation logic as M-Pesa
            $this->allocatePaymentToInvoices($payment);

            // Send payment received email
            try {
                \Log::info('Sending payment received email to client:', ['email' => $client->email]);
                \Mail::to($client->email)->send(new \App\Mail\PaymentReceived($payment->fresh()->load(['client', 'organization'])));
                \Log::info('Payment received email sent successfully');
            } catch (\Exception $e) {
                \Log::error('Failed to send payment received email:', [
                    'error' => $e->getMessage(),
                    'client_email' => $client->email
                ]);
            }

            DB::commit();
            \Log::info('=== CASH PAYMENT CREATION END - SUCCESS ===');

            return response()->json([
                'status' => true,
                'message' => 'Payment recorded successfully',
                'data' => ['payment' => $payment->fresh()->load('client')]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Cash payment creation failed:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'error' => 'Payment creation failed',
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }
}