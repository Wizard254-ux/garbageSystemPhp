<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

class PaymentReceived extends Mailable
{
    use Queueable, SerializesModels;

    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function build()
    {
        $paymentMethod = ucfirst(str_replace('_', ' ', $this->payment->payment_method));
        
        return $this->subject('Payment Received - $' . number_format($this->payment->amount, 2))
                    ->view('emails.payment-received')
                    ->with([
                        'payment' => $this->payment,
                        'client' => $this->payment->client,
                        'organization' => $this->payment->organization,
                        'paymentMethod' => $paymentMethod
                    ]);
    }
}