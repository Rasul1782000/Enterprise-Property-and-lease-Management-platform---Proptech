<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
        $this->invoice->load(['tenant','lease.unit.building','unit']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rent Invoice '.$this->invoice->invoice_number.' - Due '.$this->invoice->due_date->format('M d, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rent-invoice',
            with: ['invoice'=>$this->invoice]
        );
    }
}
