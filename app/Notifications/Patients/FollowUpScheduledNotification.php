<?php

namespace App\Notifications\Patients;

use App\Models\PatientCase;
use App\Models\PatientFollowUp;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PatientCase $case,
        public PatientFollowUp $followUp,
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
        $followUp = $this->followUp;
        $team = $case->team;
        $caseUrl = route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]);

        return CareFlowMail::message(
            subject: sprintf('Follow-up scheduled · %s', $case->reference),
            title: 'A follow-up needs your attention',
            greeting: sprintf('Hello %s,', $notifiable->name ?? 'team'),
            introLines: [
                sprintf(
                    'A follow-up was scheduled on case <strong>%s</strong>: <strong>%s</strong>.',
                    e($case->reference),
                    e($followUp->title),
                ),
                $followUp->description
                    ? e($followUp->description)
                    : 'Please complete this follow-up by the due date.',
            ],
            actionText: 'Open case',
            actionUrl: $caseUrl,
            meta: array_filter([
                'Case reference' => $case->reference,
                'Patient' => $case->patient?->fullName(),
                'Follow-up' => $followUp->title,
                'Type' => $followUp->follow_up_type->label(),
                'Due' => $followUp->due_at?->timezone(config('app.timezone'))->toDayDateTimeString(),
            ]),
            outroLines: [
                'Keeping follow-ups on time helps patients feel guided after treatment or proposal steps.',
            ],
            team: $team,
            eyebrow: 'Follow-up reminder',
            preheader: sprintf('Follow-up due %s · %s', $followUp->due_at?->toDateString() ?? 'soon', $case->reference),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'case_id' => $this->case->id,
            'follow_up_id' => $this->followUp->id,
        ];
    }
}
