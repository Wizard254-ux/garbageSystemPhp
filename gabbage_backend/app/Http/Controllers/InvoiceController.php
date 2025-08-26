<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        \Log::info('=== FETCH ALL INVOICES START ===');
        \Log::info('Request params:', $request->all());
        
        $organizationId = $request->user()->id;
        \Log::info('Organization ID:', ['org_id' => $organizationId]);
        
        $query = Invoice::where('organization_id', $organizationId)->with('client');
        
        // Add search/filter functionality
        if ($request->has('status') && !empty($request->status)) {
            \Log::info('Filtering by status:', ['status' => $request->status]);
            if (in_array($request->status, ['fully_paid', 'partially_paid', 'unpaid'])) {
                $query->where('payment_status', $request->status);
            } else {
                $query->where('status', $request->status);
            }
        }
        
        if ($request->has('startDate') && !empty($request->startDate)) {
            \Log::info('Filtering by start date:', ['start_date' => $request->startDate]);
            $query->whereDate('created_at', '>=', $request->startDate);
        }
        
        if ($request->has('endDate') && !empty($request->endDate)) {
            \Log::info('Filtering by end date:', ['end_date' => $request->endDate]);
            $query->whereDate('created_at', '<=', $request->endDate);
        }
        
        if ($request->has('accountNumber') && !empty($request->accountNumber)) {
            \Log::info('Filtering by account number:', ['account_number' => $request->accountNumber]);
            $query->whereHas('client', function($clientQuery) use ($request) {
                $clientQuery->where('id', 'like', '%' . $request->accountNumber . '%');
            });
        }
        
        if ($request->has('clientName') && !empty($request->clientName)) {
            \Log::info('Filtering by client name:', ['client_name' => $request->clientName]);
            $query->whereHas('client', function($clientQuery) use ($request) {
                $clientQuery->where('name', 'like', '%' . $request->clientName . '%');
            });
        }
        
        $invoices = $query->orderBy('created_at', 'desc')->get();
        \Log::info('Invoices fetched:', ['count' => $invoices->count()]);
        
        \Log::info('=== FETCH ALL INVOICES END ===');
        return response()->json([
            'status' => true,
            'data' => ['invoices' => $invoices]
        ], 200);
    }

    public function store(Request $request)
    {
        \Log::info('=== INVOICE CREATION START ===');
        \Log::info('Request data:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'type' => 'nullable|in:monthly,custom',
            'client_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date|after:today',
            'description' => 'nullable|string'
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

        // Verify client belongs to organization
        $client = User::where('id', $request->client_id)
            ->where('role', 'client')
            ->first();

        if (!$client) {
            return response()->json([
                'status' => false,
                'error' => 'Invalid client',
                'message' => 'Client not found'
            ], 404);
        }

        $invoice = Invoice::create([
            'title' => $request->title,
            'type' => $request->type ?? 'monthly', // Default to monthly
            'client_id' => $request->client_id,
            'organization_id' => $request->user()->id,
            'amount' => $request->amount,
            'due_date' => $request->due_date,
            'description' => $request->description
        ]);

        \Log::info('Invoice created successfully:', ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number]);
        \Log::info('Client email:', ['email' => $client->email, 'name' => $client->name]);
        
        // Send invoice email to client directly
        try {
            \Log::info('Attempting to send invoice email...');
            $invoiceWithRelations = $invoice->load(['client', 'organization']);
            \Log::info('Invoice loaded with relations:', ['client_loaded' => $invoiceWithRelations->client ? true : false, 'org_loaded' => $invoiceWithRelations->organization ? true : false]);
            
            \Mail::to($client->email)->send(new \App\Mail\InvoiceCreated($invoiceWithRelations));
            \Log::info('Invoice email sent successfully to:', ['email' => $client->email]);
        } catch (\Exception $e) {
            \Log::error('Failed to send invoice email:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'client_email' => $client->email
            ]);
        }
        
        // Check for existing payments to process this invoice
        $this->processExistingPaymentsForInvoice($invoice);
        
        \Log::info('=== INVOICE CREATION END ===');

        return response()->json([
            'status' => true,
            'message' => 'Invoice created successfully',
            'data' => ['invoice' => $invoice->fresh()->load('client')]
        ], 200);
    }

    public function show(Request $request, $id)
    {
        \Log::info('=== FETCH INVOICE DETAILS START ===');
        \Log::info('Invoice ID:', ['id' => $id]);
        
        $organizationId = $request->user()->id;
        \Log::info('Organization ID:', ['org_id' => $organizationId]);
        
        $invoice = Invoice::where('organization_id', $organizationId)
            ->with('client')
            ->find($id);

        if (!$invoice) {
            \Log::warning('Invoice not found:', ['id' => $id, 'org_id' => $organizationId]);
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Invoice not found'
            ], 404);
        }
        
        // Get payment details if payment_ids exist
        $payments = [];
        if (!empty($invoice->payment_ids)) {
            $payments = \App\Models\Payment::whereIn('id', $invoice->payment_ids)
                ->select('id', 'trans_id', 'first_name', 'amount', 'payment_method', 'status', 'created_at')
                ->get();
            \Log::info('Found payments for invoice:', ['payment_count' => $payments->count()]);
        }
        
        $invoice->payments = $payments;
        
        \Log::info('Invoice found:', ['invoice_number' => $invoice->invoice_number, 'amount' => $invoice->amount]);
        \Log::info('=== FETCH INVOICE DETAILS END ===');

        return response()->json([
            'status' => true,
            'data' => ['invoice' => $invoice]
        ], 200);
    }

    public function getClientInvoices(Request $request, $clientId)
    {
        \Log::info('=== FETCH CLIENT INVOICES START ===');
        \Log::info('Client ID:', ['client_id' => $clientId]);
        
        $organizationId = $request->user()->id;
        \Log::info('Organization ID:', ['org_id' => $organizationId]);
        
        // Verify client belongs to organization
        $client = User::where('id', $clientId)
            ->where('role', 'client')
            ->first();

        if (!$client) {
            \Log::warning('Client not found:', ['client_id' => $clientId]);
            return response()->json([
                'status' => false,
                'error' => 'Not found',
                'message' => 'Client not found'
            ], 404);
        }
        
        \Log::info('Client found:', ['client_name' => $client->name, 'client_email' => $client->email]);

        $invoices = Invoice::where('client_id', $clientId)
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        \Log::info('Client invoices fetched:', ['count' => $invoices->count()]);

        // Transform invoices to include transaction IDs instead of payment IDs
        $invoices->transform(function ($invoice) {
            if (!empty($invoice->payment_ids)) {
                $transactionIds = \App\Models\Payment::whereIn('id', $invoice->payment_ids)
                    ->pluck('trans_id')
                    ->toArray();
                $invoice->transaction_ids = $transactionIds;
            } else {
                $invoice->transaction_ids = [];
            }
            return $invoice;
        });
        
        \Log::info('=== FETCH CLIENT INVOICES END ===');

        return response()->json([
            'status' => true,
            'data' => ['invoices' => $invoices]
        ], 200);
    }

    public function resendInvoices(Request $request)
    {
        \Log::info('=== RESEND INVOICES START ===');
        \Log::info('Request data:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'required|integer|exists:invoices,id'
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
        $invoiceIds = $request->invoice_ids;
        
        // Verify all invoices belong to the organization
        $invoices = Invoice::whereIn('id', $invoiceIds)
            ->where('organization_id', $organizationId)
            ->with('client')
            ->get();

        if ($invoices->count() !== count($invoiceIds)) {
            return response()->json([
                'status' => false,
                'error' => 'Invalid invoices',
                'message' => 'Some invoices not found or do not belong to your organization'
            ], 404);
        }

        \Log::info('Found invoices to resend:', ['count' => $invoices->count()]);
        
        // Send emails directly for each invoice
        $sentCount = 0;
        $failedCount = 0;
        
        foreach ($invoices as $invoice) {
            if ($invoice->client && $invoice->client->email) {
                try {
                    \Log::info('Sending email for invoice:', [
                        'invoice_id' => $invoice->id,
                        'client_email' => $invoice->client->email
                    ]);
                    
                    \Mail::to($invoice->client->email)->send(new \App\Mail\InvoiceCreated($invoice));
                    $sentCount++;
                    
                    \Log::info('Email sent successfully for invoice:', [
                        'invoice_id' => $invoice->id,
                        'client_email' => $invoice->client->email
                    ]);
                } catch (\Exception $e) {
                    $failedCount++;
                    \Log::error('Failed to send email for invoice:', [
                        'invoice_id' => $invoice->id,
                        'client_email' => $invoice->client->email,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                $failedCount++;
                \Log::warning('Skipped invoice - no client or email:', ['invoice_id' => $invoice->id]);
            }
        }

        \Log::info('=== RESEND INVOICES END ===', ['sent_count' => $sentCount, 'failed_count' => $failedCount]);
        
        return response()->json([
            'status' => true,
            'message' => "Successfully sent {$sentCount} invoice emails. {$failedCount} failed.",
            'data' => [
                'total_invoices' => $invoices->count(),
                'sent_emails' => $sentCount,
                'failed_emails' => $failedCount
            ]
        ], 200);
    }

    private function processExistingPaymentsForInvoice(Invoice $invoice)
    {
        \Log::info('=== CHECKING EXISTING PAYMENTS FOR NEW INVOICE ===');
        \Log::info('Invoice details:', ['id' => $invoice->id, 'client_id' => $invoice->client_id, 'amount' => $invoice->amount]);
        
        // Find payments with remaining balance for this client
        $payments = \App\Models\Payment::where('client_id', $invoice->client_id)
            ->whereIn('status', ['not_allocated', 'partially_allocated'])
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        \Log::info('Found payments with remaining balance:', ['count' => $payments->count()]);

        if ($payments->isEmpty()) {
            \Log::info('No existing payments to process');
            return;
        }

        $invoiceAmountDue = $invoice->amount - $invoice->paid_amount;
        \Log::info('Invoice amount due:', ['amount' => $invoiceAmountDue]);

        foreach ($payments as $payment) {
            if ($invoiceAmountDue <= 0) break;

            $availableAmount = $payment->remaining_amount;
            $paymentAmount = min($availableAmount, $invoiceAmountDue);

            \Log::info('Processing payment for invoice:', [
                'payment_id' => $payment->id,
                'available_amount' => $availableAmount,
                'payment_amount' => $paymentAmount
            ]);

            // Update invoice
            $invoice->paid_amount += $paymentAmount;
            $invoice->payment_status = $invoice->paid_amount >= $invoice->amount ? 'fully_paid' : 'partially_paid';
            
            $paymentIds = $invoice->payment_ids ?? [];
            if (!in_array($payment->id, $paymentIds)) {
                $paymentIds[] = $payment->id;
                $invoice->payment_ids = $paymentIds;
            }
            $invoice->save();

            // Update payment
            $payment->remaining_amount -= $paymentAmount;
            $payment->allocated_amount += $paymentAmount;
            
            $invoicesProcessed = $payment->invoices_processed ?? [];
            if (!in_array($invoice->id, $invoicesProcessed)) {
                $invoicesProcessed[] = $invoice->id;
                $payment->invoices_processed = $invoicesProcessed;
            }
            
            $payment->status = $payment->remaining_amount > 0 ? 'partially_allocated' : 'fully_allocated';
            $payment->save();

            $invoiceAmountDue -= $paymentAmount;

            \Log::info('Payment processed:', [
                'payment_id' => $payment->id,
                'payment_status' => $payment->status,
                'invoice_paid_amount' => $invoice->paid_amount,
                'invoice_status' => $invoice->payment_status
            ]);
        }

        \Log::info('=== EXISTING PAYMENTS PROCESSING COMPLETED ===');
    }

    public function getAgingSummary(Request $request)
    {
        \Log::info('=== FETCH AGING SUMMARY START ===');
        
        $organizationId = $request->user()->id;
        $gracePeriodDays = 5; // 7-day grace period
        
        // Get all unpaid/partially paid invoices that are past due date + grace period
        $overdueInvoices = Invoice::where('organization_id', $organizationId)
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->whereRaw('DATE_ADD(due_date, INTERVAL ? DAY) < NOW()', [$gracePeriodDays])
            ->with('client')
            ->get();
        
        \Log::info('Found overdue invoices:', ['count' => $overdueInvoices->count()]);
        
        // Calculate aging buckets
        $buckets = [
            ['range' => '1-30 days', 'min' => 1, 'max' => 30],
            ['range' => '31-60 days', 'min' => 31, 'max' => 60],
            ['range' => '61-90 days', 'min' => 61, 'max' => 90],
            ['range' => '90+ days', 'min' => 91, 'max' => 999999]
        ];
        
        $agingBuckets = [];
        $totalUnpaidAmount = 0;
        
        foreach ($buckets as $bucket) {
            $bucketInvoices = $overdueInvoices->filter(function ($invoice) use ($bucket, $gracePeriodDays) {
                $dueDate = new \DateTime($invoice->due_date);
                $graceEndDate = $dueDate->modify("+{$gracePeriodDays} days");
                $now = new \DateTime();
                $daysOverdue = $now->diff($graceEndDate)->days;
                
                return $daysOverdue >= $bucket['min'] && $daysOverdue <= $bucket['max'];
            });
            
            $bucketAmount = $bucketInvoices->sum(function ($invoice) {
                return floatval($invoice->amount) - floatval($invoice->paid_amount);
            });
            
            $agingBuckets[] = [
                'range' => $bucket['range'],
                'count' => $bucketInvoices->count(),
                'totalAmount' => $bucketAmount,
                'percentage' => 0 // Will calculate after getting total
            ];
            
            $totalUnpaidAmount += $bucketAmount;
        }
        
        // Calculate percentages
        foreach ($agingBuckets as &$bucket) {
            $bucket['percentage'] = $totalUnpaidAmount > 0 ? ($bucket['totalAmount'] / $totalUnpaidAmount) * 100 : 0;
        }
        
        $summary = [
            'totalUnpaidAmount' => $totalUnpaidAmount,
            'totalInvoices' => $overdueInvoices->count(),
            'overdueCount' => $overdueInvoices->count(),
            'overdueAmount' => $totalUnpaidAmount,
            'dueCount' => 0,
            'dueAmount' => 0,
            'agingBuckets' => $agingBuckets,
            'gracePeriodDays' => $gracePeriodDays,
            'message' => "Aging calculation starts after {$gracePeriodDays}-day grace period from due date"
        ];
        
        \Log::info('Aging summary calculated:', ['total_amount' => $totalUnpaidAmount, 'total_invoices' => $overdueInvoices->count()]);
        \Log::info('=== FETCH AGING SUMMARY END ===');
        
        return response()->json([
            'status' => true,
            'data' => ['summary' => $summary]
        ], 200);
    }
}