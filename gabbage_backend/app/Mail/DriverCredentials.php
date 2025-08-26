<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DriverCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $password;
    public $name;

    public function __construct($email, $password, $name)
    {
        $this->email = $email;
        $this->password = $password;
        $this->name = $name;
    }

    public function build()
    {
        return $this->subject('Your Driver Account Credentials')
                    ->view('emails.driver-credentials')
                    ->with([
                        'email' => $this->email,
                        'password' => $this->password,
                        'name' => $this->name,
                    ]);
    }
}