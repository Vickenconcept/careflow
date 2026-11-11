<?php

namespace App\Notifications\Patients;

use App\Models\PatientCase;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PatientPortalUpdateStaffNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PatientCase $case,
        public string $title,
        public string $kind,
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
            subject: sprintf('%s · %s', $this->title, $case->reference),
            title: $this->title,
            greeting: sprintf('Hello %s,', $notifiable->name ?? 'team'),
            introLines: [
                sprintf(
                    '<strong>%s</strong> sent an update through the secure tracking page. Please review and respond.',
                    e($case->patient?->fullName() ?? 'A patient'),
                ),
            ],
            actionText: 'Open case',
            actionUrl: route('organizations.cases.show', ['current_team' => $team, 'case' => $case]),
            meta: array_filter([
                'Case reference' => $case->reference,
                'Patient' => $case->patient?->fullName(),
                'Status' => $case->status->label(),
            ]),
            outroLines: [],
            team: $team,
            eyebrow: 'Patient update',
            preheader: $this->title,
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
            'kind' => $this->kind,
        ];
    }
}
