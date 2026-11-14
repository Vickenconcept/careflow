<?php

namespace App\Notifications\Patients;

use App\Models\PatientFollowUp;
use App\Models\Team;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Morning email to a staff member listing their follow-ups that are due today or overdue.
 */
class FollowUpsDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, PatientFollowUp>  $followUps
     */
    public function __construct(
        public Team $team,
        public Collection $followUps,
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
        $team = $this->team->loadMissing('organizationSetting');
        $followUps = $this->followUps->loadMissing('case.patient');
        $timezone = $team->organizationSetting?->timezone ?: config('app.timezone');
        $startOfToday = now($timezone)->startOfDay();
        $count = $followUps->count();

        $lines = $followUps
            ->map(function (PatientFollowUp $followUp) use ($team, $timezone, $startOfToday): string {
                $case = $followUp->case;
                $url = route('organizations.cases.show', [
                    'current_team' => $team,
                    'case' => $case,
                    'tab' => 'tasks',
                ]);
                $isOverdue = $followUp->due_at !== null && $followUp->due_at->lt($startOfToday);

                return sprintf(
                    '<a href="%s">%s</a> · <strong>%s</strong> · %s<br><span style="color:#6b7a70;">%s</span>',
                    e($url),
                    e($case->reference),
                    e($followUp->title),
                    e($case->patient?->fullName() ?? 'Patient'),
                    $isOverdue
                        ? e(sprintf('Overdue since %s', $followUp->due_at->timezone($timezone)->toFormattedDateString()))
                        : 'Due today',
                );
            })
            ->values()
            ->all();

        return CareFlowMail::message(
            subject: $count === 1 ? '1 follow-up needs your attention today' : sprintf('%d follow-ups need your attention today', $count),
            title: 'Follow-ups for today',
            greeting: sprintf('Hello %s,', $notifiable->name ?? 'team'),
            introLines: [
                $count === 1
                    ? 'This follow-up is due today or is already late:'
                    : 'These follow-ups are due today or are already late:',
                ...$lines,
            ],
            actionText: null,
            actionUrl: null,
            meta: [],
            outroLines: [
                'Please contact the patient or mark the follow-up as done once it is handled.',
            ],
            team: $team,
            eyebrow: 'Follow-up reminder',
            preheader: $count === 1 ? '1 follow-up due today' : sprintf('%d follow-ups due today', $count),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->team->id,
            'follow_up_ids' => $this->followUps->modelKeys(),
        ];
    }
}
