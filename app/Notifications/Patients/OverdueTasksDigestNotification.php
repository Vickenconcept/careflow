<?php

namespace App\Notifications\Patients;

use App\Models\CaseTask;
use App\Models\Team;
use App\Support\CareFlowMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Once-a-day email to a staff member listing their tasks that are past their due date.
 */
class OverdueTasksDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, CaseTask>  $tasks
     */
    public function __construct(
        public Team $team,
        public Collection $tasks,
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
        $tasks = $this->tasks->loadMissing('case.patient');
        $timezone = $team->organizationSetting?->timezone ?: config('app.timezone');
        $count = $tasks->count();

        $lines = $tasks
            ->map(function (CaseTask $task) use ($team, $timezone): string {
                $case = $task->case;
                $url = route('organizations.cases.show', [
                    'current_team' => $team,
                    'case' => $case,
                    'tab' => 'tasks',
                ]);

                return sprintf(
                    '<a href="%s">%s</a> · <strong>%s</strong> · %s<br><span style="color:#6b7a70;">%s</span>',
                    e($url),
                    e($case->reference),
                    e($task->title),
                    e($case->patient?->fullName() ?? 'Patient'),
                    e(sprintf('Was due %s', $task->due_at?->timezone($timezone)->toFormattedDateString() ?? 'earlier')),
                );
            })
            ->values()
            ->all();

        return CareFlowMail::message(
            subject: $count === 1 ? 'You have 1 overdue task' : sprintf('You have %d overdue tasks', $count),
            title: 'Overdue tasks',
            greeting: sprintf('Hello %s,', $notifiable->name ?? 'team'),
            introLines: [
                $count === 1
                    ? 'This task is past its due date:'
                    : 'These tasks are past their due date:',
                ...$lines,
            ],
            actionText: null,
            actionUrl: null,
            meta: [],
            outroLines: [
                'If a task is no longer needed, you can cancel it or change its due date on the case.',
            ],
            team: $team,
            eyebrow: 'Task reminder',
            preheader: $count === 1 ? '1 overdue task' : sprintf('%d overdue tasks', $count),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->team->id,
            'task_ids' => $this->tasks->modelKeys(),
        ];
    }
}
