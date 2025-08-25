<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Pickup;

class PickupCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public $pickup;

    public function __construct(Pickup $pickup)
    {
        $this->pickup = $pickup;
    }

    public function build()
    {
        return $this->subject('Garbage Collection Completed - ' . $this->pickup->pickup_date->format('F d, Y'))
                    ->view('emails.pickup-completed')
                    ->with([
                        'pickup' => $this->pickup,
                        'client' => $this->pickup->client,
                        'driver' => $this->pickup->driver,
                        'route' => $this->pickup->route,
                    ]);
    }
}