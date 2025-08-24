<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $password,
        public string $name
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Organization Account Credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-credentials',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
