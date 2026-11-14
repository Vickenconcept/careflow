<?php

namespace App\Notifications\Patients;

use App\Models\PatientCase;
use App\Models\TreatmentProposal;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the case coordinator that a proposal sent to the patient passed its valid-until date.
 */
class TreatmentProposalExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PatientCase $case,
        public TreatmentProposal $proposal,
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
        $team = $case->team;

        return CareFlowMail::message(
            subject: sprintf('Proposal expired · %s', $case->reference),
            title: 'A treatment proposal has expired',
            greeting: sprintf('Hello %s,', $notifiable->name ?? 'team'),
            introLines: [
                sprintf(
                    'The proposal <strong>%s</strong> for <strong>%s</strong> passed its valid-until date without an answer from the patient, so it is now marked as expired.',
                    e($this->proposal->reference),
                    e($case->patient?->fullName() ?? 'the patient'),
                ),
                'If the patient is still interested, you can prepare a revised proposal from the case.',
            ],
            actionText: 'Open proposal',
            actionUrl: route('organizations.cases.show', [
                'current_team' => $team,
                'case' => $case,
                'tab' => 'proposal',
            ]),
            meta: array_filter([
                'Case reference' => $case->reference,
                'Patient' => $case->patient?->fullName(),
                'Proposal' => $this->proposal->reference,
                'Valid until' => $this->proposal->valid_until?->toFormattedDateString(),
            ]),
            outroLines: [],
            team: $team,
            eyebrow: 'Proposal update',
            preheader: sprintf('Proposal %s expired', $this->proposal->reference),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'case_id' => $this->case->id,
            'proposal_id' => $this->proposal->id,
        ];
    }
}
