<?php

namespace App\Notifications\Patients;

use App\Models\PatientCase;
use App\Models\User;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PatientCase $case,
        public string $roleLabel,
        public User $assignedBy,
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
        $caseUrl = route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]);

        return CareFlowMail::message(
            subject: sprintf('You were assigned · %s', $case->reference),
            title: sprintf('Assigned as %s', $this->roleLabel),
            greeting: sprintf('Hello %s,', $notifiable->name ?? 'there'),
            introLines: [
                sprintf(
                    '<strong>%s</strong> assigned you as <strong>%s</strong> on case %s for patient <strong>%s</strong>.',
                    e($this->assignedBy->name),
                    e($this->roleLabel),
                    e($case->reference),
                    e($case->patient?->fullName() ?? 'Unknown'),
                ),
            ],
            actionText: 'Review case',
            actionUrl: $caseUrl,
            meta: array_filter([
                'Case reference' => $case->reference,
                'Role' => $this->roleLabel,
                'Status' => $case->status->label(),
                'Department' => $case->department?->name,
            ]),
            outroLines: [
                'Please review the enquiry, documents, and next required action when you are ready.',
            ],
            team: $team,
            eyebrow: 'Assignment',
            preheader: sprintf('Assigned to %s on %s', $this->roleLabel, $case->reference),
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
            'role' => $this->roleLabel,
        ];
    }
}
