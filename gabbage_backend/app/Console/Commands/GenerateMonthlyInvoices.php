<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\Invoice;
use App\Mail\InvoiceCreated;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate-monthly';
    protected $description = 'Generate monthly invoices for all active clients';

    public function handle()
    {
        \Log::info('=== INVOICE GENERATION COMMAND STARTED ===');
        \Log::info('Current time:', ['time' => Carbon::now()->toDateTimeString()]);
        \Log::info('Environment:', ['env' => app()->environment()]);
        
        $this->info('=== STARTING MONTHLY INVOICE GENERATION ===');
        
        $generatedCount = 0;
        $overdueCount = 0;
        $failedCount = 0;
        $now = Carbon::now();
        
        \Log::info('Processing timestamp:', ['now' => $now->toDateTimeString()]);

        // Get all active clients with monthly rates and service start dates
        \Log::info('Querying clients...');
        $clients = Client::with(['user', 'organization'])
            ->whereNotNull('monthlyRate')
            ->where('monthlyRate', '>', 0)
            ->whereNotNull('serviceStartDate')
            ->get();

        \Log::info('Clients found:', ['count' => $clients->count()]);
        $this->info("Found {$clients->count()} clients to process");
        
        if ($clients->isEmpty()) {
            \Log::warning('No clients found with monthly rates and service start dates');
            $this->warn('No clients found to process');
            return 0;
        }

        foreach ($clients as $client) {
            try {
                \Log::info('Processing client:', [
                    'client_id' => $client->id,
                    'user_id' => $client->user_id,
                    'name' => $client->user->name ?? 'Unknown',
                    'email' => $client->user->email ?? 'Unknown',
                    'monthly_rate' => $client->monthlyRate,
                    'service_start_date' => $client->serviceStartDate
                ]);
                
                $serviceStartDate = Carbon::parse($client->serviceStartDate);
                $monthsSinceStart = $serviceStartDate->diffInMonths($now);
                
                \Log::info('Service period calculation:', [
                    'service_start' => $serviceStartDate->toDateString(),
                    'months_since_start' => $monthsSinceStart,
                    'current_date' => $now->toDateString()
                ]);
                
                $this->info("Processing client {$client->user->name} - Service started: {$serviceStartDate->format('Y-m-d')}, Months since: {$monthsSinceStart}");

                // Skip if service hasn't reached one month yet
                if ($monthsSinceStart < 1) {
                    \Log::info('Skipping client - service not reached one month:', [
                        'client' => $client->user->name,
                        'months_since_start' => $monthsSinceStart
                    ]);
                    $this->info("Service hasn't reached one month yet for {$client->user->name}");
                    continue;
                }

                // Calculate which month we should generate invoice for
                $invoiceMonth = $serviceStartDate->copy()->addMonths($monthsSinceStart);
                
                \Log::info('Invoice month calculation:', [
                    'invoice_month' => $invoiceMonth->toDateString(),
                    'invoice_month_name' => $invoiceMonth->format('F Y')
                ]);
                
                // Check if monthly invoice already exists for this billing period
                \Log::info('Checking for existing monthly invoice...');
                $existingInvoice = Invoice::where('client_id', $client->user_id)
                    ->where('organization_id', $client->organization_id)
                    ->where('type', 'monthly') // Only check monthly invoices
                    ->whereMonth('created_at', $invoiceMonth->month)
                    ->whereYear('created_at', $invoiceMonth->year)
                    ->first();
                    
                \Log::info('Existing invoice check result:', [
                    'exists' => $existingInvoice ? true : false,
                    'invoice_id' => $existingInvoice->id ?? null
                ]);

                if ($existingInvoice) {
                    \Log::info('Existing invoice found, checking overdue status...');
                    // Check if invoice is overdue (past grace period)
                    $gracePeriod = $client->gracePeriod ?? 0; // Default 0 days if not set
                    $dueDate = Carbon::parse($existingInvoice->due_date);
                    $overdueDate = $dueDate->copy()->addDays($gracePeriod);
                    
                    \Log::info('Overdue calculation:', [
                        'due_date' => $dueDate->toDateString(),
                        'grace_period' => $gracePeriod,
                        'overdue_date' => $overdueDate->toDateString(),
                        'is_overdue' => $now->greaterThan($overdueDate),
                        'payment_status' => $existingInvoice->payment_status
                    ]);
                    
                    if ($now->greaterThan($overdueDate) && $existingInvoice->payment_status !== 'fully_paid') {
                        \Log::info('Sending overdue notice:', ['invoice_number' => $existingInvoice->invoice_number]);
                        $this->info("Sending overdue notice for invoice {$existingInvoice->invoice_number} to {$client->user->name}");
                        $this->sendOverdueNotice($existingInvoice, $client);
                        $overdueCount++;
                    } else {
                        \Log::info('Invoice exists but not overdue or already paid');
                        $this->info("Invoice exists and not overdue for {$client->user->name}");
                    }
                    continue;
                }

                // Create new monthly invoice
                \Log::info('Creating new monthly invoice...');
                $invoiceData = [
                    'title' => 'Monthly Garbage Collection Service - ' . $invoiceMonth->format('F Y'),
                    'type' => 'monthly', // Explicitly set as monthly
                    'client_id' => $client->user_id,
                    'organization_id' => $client->organization_id,
                    'amount' => $client->monthlyRate,
                    'due_date' => $now->copy()->addDays(30),
                    'description' => "Monthly garbage collection service fee for {$invoiceMonth->format('F Y')}"
                ];
                
                \Log::info('Invoice data:', $invoiceData);
                
                $invoice = Invoice::create($invoiceData);

                \Log::info('Invoice created successfully:', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $invoice->amount
                ]);
                
                $this->info("Created invoice {$invoice->invoice_number} for {$client->user->name}");

                // Check for existing payments to process this invoice
                $this->processExistingPaymentsForInvoice($invoice);

                // Send invoice email
                try {
                    Mail::to($client->user->email)->send(new InvoiceCreated($invoice->load(['client', 'organization'])));
                    $this->info("Email sent to {$client->user->email}");
                } catch (\Exception $e) {
                    $this->error("Failed to send email to {$client->user->email}: " . $e->getMessage());
                }

                $generatedCount++;

            } catch (\Exception $e) {
                \Log::error('Failed to process client:', [
                    'client_id' => $client->id ?? 'unknown',
                    'client_name' => $client->user->name ?? 'unknown',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->error("Failed to process client {$client->user->name}: " . $e->getMessage());
                $failedCount++;
            }
        }

        \Log::info('=== INVOICE GENERATION COMMAND COMPLETED ===', [
            'generated_count' => $generatedCount,
            'overdue_count' => $overdueCount,
            'failed_count' => $failedCount,
            'total_processed' => $clients->count(),
            'completion_time' => Carbon::now()->toDateTimeString()
        ]);
        
        $this->info("=== MONTHLY INVOICE GENERATION COMPLETED ===");
        $this->info("Generated: {$generatedCount} new invoices");
        $this->info("Overdue notices: {$overdueCount} sent");
        $this->info("Failed: {$failedCount} invoices");

        return 0;
    }

    private function processExistingPaymentsForInvoice(Invoice $invoice)
    {
        $payments = \App\Models\Payment::where('client_id', $invoice->client_id)
            ->whereIn('status', ['not_allocated', 'partially_allocated'])
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($payments->isEmpty()) {
            return;
        }

        $invoiceAmountDue = $invoice->amount - $invoice->paid_amount;

        foreach ($payments as $payment) {
            if ($invoiceAmountDue <= 0) break;

            $availableAmount = $payment->remaining_amount;
            $paymentAmount = min($availableAmount, $invoiceAmountDue);

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

            $this->info("Applied payment {$payment->id} ({$paymentAmount}) to invoice {$invoice->id}");
        }
    }

    private function sendOverdueNotice(Invoice $invoice, Client $client)
    {
        try {
            Mail::to($client->user->email)->send(new \App\Mail\OverdueInvoice($invoice->load(['client', 'organization']), $client));
            $this->info("Overdue notice sent to {$client->user->email}");
        } catch (\Exception $e) {
            $this->error("Failed to send overdue notice to {$client->user->email}: " . $e->getMessage());
        }
    }
}