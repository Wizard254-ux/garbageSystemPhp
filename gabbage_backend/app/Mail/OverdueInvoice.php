<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use App\Models\Client;

class OverdueInvoice extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $client;

    public function __construct(Invoice $invoice, Client $client)
    {
        $this->invoice = $invoice;
        $this->client = $client;
    }

    public function build()
    {
        return $this->subject('OVERDUE: Invoice ' . $this->invoice->invoice_number)
                    ->view('emails.overdue-invoice')
                    ->with([
                        'invoice' => $this->invoice,
                        'client' => $this->client,
                        'organization' => $this->invoice->organization,
                    ]);
    }
}