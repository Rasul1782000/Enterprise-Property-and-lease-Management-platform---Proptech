<?php

namespace App\Mail;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaseExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lease $lease) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Lease Expiring Soon - '.$this->lease->lease_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lease-expiry', with: ['lease'=>$this->lease]);
    }
}
