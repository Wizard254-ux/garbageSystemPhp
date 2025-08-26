<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Models\Invoice;
use App\Mail\InvoiceCreated;

class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $invoiceId;

    public function __construct($invoiceId)
    {
        $this->invoiceId = $invoiceId;
    }

    public function handle(): void
    {
        \Log::info('=== QUEUE JOB START - SendInvoiceEmail ===');
        \Log::info('Processing invoice email job for invoice ID:', ['invoice_id' => $this->invoiceId]);
        
        $invoice = Invoice::with(['client', 'organization'])->find($this->invoiceId);
        
        if (!$invoice) {
            \Log::error('Invoice not found for email job:', ['invoice_id' => $this->invoiceId]);
            \Log::info('=== QUEUE JOB END - Invoice Not Found ===');
            return;
        }

        \Log::info('Invoice found:', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'title' => $invoice->title,
            'amount' => $invoice->amount
        ]);

        if (!$invoice->client) {
            \Log::error('Client not found for invoice:', ['invoice_id' => $this->invoiceId]);
            \Log::info('=== QUEUE JOB END - Client Not Found ===');
            return;
        }

        \Log::info('Client found:', [
            'client_id' => $invoice->client->id,
            'client_name' => $invoice->client->name,
            'client_email' => $invoice->client->email
        ]);

        if (!$invoice->organization) {
            \Log::error('Organization not found for invoice:', ['invoice_id' => $this->invoiceId]);
            \Log::info('=== QUEUE JOB END - Organization Not Found ===');
            return;
        }

        \Log::info('Organization found:', [
            'org_id' => $invoice->organization->id,
            'org_name' => $invoice->organization->name,
            'org_email' => $invoice->organization->email
        ]);

        try {
            \Log::info('Attempting to send email via Mail facade...');
            \Log::info('Email recipient:', ['to' => $invoice->client->email]);
            
            Mail::to($invoice->client->email)->send(new InvoiceCreated($invoice));
            
            \Log::info('Mail::send() completed without exception');
            \Log::info('Invoice email sent successfully:', [
                'invoice_id' => $this->invoiceId,
                'invoice_number' => $invoice->invoice_number,
                'client_email' => $invoice->client->email,
                'client_name' => $invoice->client->name
            ]);
            \Log::info('=== QUEUE JOB END - SUCCESS ===');
            
        } catch (\Exception $e) {
            \Log::error('Failed to send invoice email in job:', [
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            \Log::info('=== QUEUE JOB END - FAILED ===');
            throw $e;
        }
    }
}