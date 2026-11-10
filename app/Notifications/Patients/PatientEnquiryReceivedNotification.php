<?php

namespace App\Notifications\Patients;

use App\Models\PatientCase;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PatientEnquiryReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PatientCase $case,
        public string $portalToken,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $case = $this->case->loadMissing(['team.organizationSetting', 'patient', 'department']);
        $team = $case->team;
        $patientName = $case->patient?->first_name ?: 'there';
        $trackingUrl = route('portal.cases.show', [
            'organization' => $team->slug,
            'reference' => $case->reference,
            'token' => $this->portalToken,
        ]);

        return CareFlowMail::message(
            subject: sprintf('We received your enquiry · %s', $case->reference),
            title: 'Your enquiry is safely with us',
            greeting: sprintf('Hello %s,', $patientName),
            introLines: [
                sprintf(
                    'Thank you for contacting <strong>%s</strong>. Your international patient enquiry has been received and a care case has been opened for you.',
                    e($team->organizationSetting?->legal_name ?: $team->name),
                ),
                'Please keep your case reference. You can track progress any time with the secure link below.',
            ],
            actionText: 'Track my case',
            actionUrl: $trackingUrl,
            meta: array_filter([
                'Case reference' => $case->reference,
                'Status' => $case->status->label(),
                'Department' => $case->department?->name,
            ]),
            outroLines: [
                'If you still need to send medical documents, upload them through the tracking page when requested by the hospital team.',
                'This message confirms receipt only. A coordinator will review your enquiry shortly.',
            ],
            team: $team,
            eyebrow: 'Patient confirmation',
            preheader: sprintf('Enquiry %s received — track your case securely.', $case->reference),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'case_id' => $this->case->id,
            'reference' => $this->case->reference,
        ];
    }
}
