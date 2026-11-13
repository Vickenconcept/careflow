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

    /**
     * @param  string|null  $portalUrl  Secure link where the patient can read the conversation and reply.
     */
    public function __construct(
        public CaseCommunication $communication,
        public ?string $portalUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->communication->subject ?: 'A message about your care',
        );
    }

    public function content(): Content
    {
        $case = $this->communication->case;
        $team = $case?->team;
        $subject = $this->communication->subject ?: 'A message about your care';

        return new Content(
            view: 'mail.careflow.layout',
            text: 'mail.case-communication-text',
            with: [
                'subject' => $subject,
                'title' => $subject,
                'greeting' => null,
                'introLines' => $this->paragraphs($this->communication->body),
                'actionText' => $this->portalUrl !== null ? 'View or reply to your case' : null,
                'actionUrl' => $this->portalUrl,
                'meta' => array_filter(['Case reference' => $case?->reference]),
                'outroLines' => $this->portalUrl !== null
                    ? ['To reply, use the button above so your message reaches the team handling your case.']
                    : [],
                'organizationName' => $team?->organizationSetting?->legal_name ?: $team?->name,
                'organizationContact' => $team?->organizationSetting?->contact_email,
                'eyebrow' => 'International patient care',
                'preheader' => null,
                'body' => $this->communication->body,
                'reference' => $case?->reference,
                'portalUrl' => $this->portalUrl,
            ],
        );
    }

    /**
     * Split the message into escaped HTML paragraphs, keeping single line breaks.
     *
     * @return list<string>
     */
    protected function paragraphs(string $body): array
    {
        return collect(preg_split('/\R{2,}/', trim($body)) ?: [])
            ->map(fn (string $paragraph): string => nl2br(e(trim($paragraph))))
            ->filter()
            ->values()
            ->all();
    }
}
