<?php

namespace App\Notifications\Patients;

use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PatientCase $case,
        public PatientDocument $document,
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
        $case = $this->case->loadMissing(['team.organizationSetting', 'patient']);
        $document = $this->document;
        $team = $case->team;
        $reviewUrl = route('organizations.documents.show', [
            'current_team' => $team,
            'case' => $case,
            'document' => $document,
        ]);

        return CareFlowMail::message(
            subject: sprintf('Document ready for review · %s', $case->reference),
            title: 'AI document assist finished',
            greeting: sprintf('Hello %s,', $notifiable->name ?? 'team'),
            introLines: [
                sprintf(
                    'Document processing finished for <strong>%s</strong> on case <strong>%s</strong>.',
                    e($document->original_filename),
                    e($case->reference),
                ),
                'Open the comparison workspace to review extraction, translation, and the draft summary. AI output stays a draft until a human reviews it.',
            ],
            actionText: 'Review document',
            actionUrl: $reviewUrl,
            meta: array_filter([
                'Case reference' => $case->reference,
                'Patient' => $case->patient?->fullName(),
                'Document' => $document->original_filename,
                'Processing' => $document->processing_status?->label() ?? (string) $document->processing_status?->value,
            ]),
            outroLines: [
                'Never treat AI-generated content as clinically approved without human review.',
            ],
            team: $team,
            eyebrow: 'Documents + AI',
            preheader: sprintf('%s is ready on %s', $document->original_filename, $case->reference),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'case_id' => $this->case->id,
            'document_id' => $this->document->id,
        ];
    }
}
