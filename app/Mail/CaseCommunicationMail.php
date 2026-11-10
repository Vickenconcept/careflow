<?php

namespace App\Mail;

use App\Models\CaseCommunication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaseCommunicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CaseCommunication $communication) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->communication->subject ?: 'CareFlow message',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.case-communication-text',
            with: [
                'body' => $this->communication->body,
                'reference' => $this->communication->case?->reference,
            ],
        );
    }
}
