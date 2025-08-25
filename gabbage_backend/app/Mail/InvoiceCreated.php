<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;

class InvoiceCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function build()
    {
        return $this->subject('New Invoice - ' . $this->invoice->invoice_number)
                    ->view('emails.invoice-created')
                    ->with([
                        'invoice' => $this->invoice,
                        'client' => $this->invoice->client,
                        'organization' => $this->invoice->organization,
                    ]);
    }
}