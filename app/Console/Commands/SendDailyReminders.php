<?php

namespace App\Console\Commands;

use App\Actions\Patients\NotifyPatient;
use App\Actions\Patients\RecordCaseActivity;
use App\Domains\Proposals\TreatmentProposalTransitionMap;
use App\Enums\CaseActivityType;
use App\Enums\CaseTaskStatus;
use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Enums\MessageTemplateKey;
use App\Enums\TreatmentProposalStatus;
use App\Models\CaseTask;
use App\Models\PatientFollowUp;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;
use App\Notifications\Patients\FollowUpsDueNotification;
use App\Notifications\Patients\OverdueTasksDigestNotification;
use App\Notifications\Patients\TreatmentProposalExpiredNotification;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily automation that makes dates on cases do something.
 *
 * Per organization it emails staff about follow-ups due today or late, prepares a
 * follow-up reminder for the patient (as a draft for staff to approve), emails one
 * digest of overdue tasks per person, and marks proposals that passed their
 * valid-until date as expired. Running it more than once a day is safe: every
 * reminder is stamped so it is only sent once per day.
 */
#[Signature('careflow:daily-reminders {--local-hour= : Only handle organizations where it is currently this hour (0-23) in their own time zone}')]
#[Description('Send daily follow-up and overdue task reminders and expire out-of-date treatment proposals')]
class SendDailyReminders extends Command
{
    /**
     * Follow-up types that are about the patient. Administrative follow-ups are internal only.
     *
     * @var list<FollowUpType>
     */
    private const PATIENT_FACING_FOLLOW_UP_TYPES = [
        FollowUpType::ClinicalCheckIn,
        FollowUpType::TravelReturn,
        FollowUpType::OutcomeReview,
        FollowUpType::Other,
    ];

    /**
     * @var array{follow_up_emails: int, patient_drafts: int, task_digests: int, expired_proposals: int}
     */
    private array $totals = [
        'follow_up_emails' => 0,
        'patient_drafts' => 0,
        'task_digests' => 0,
        'expired_proposals' => 0,
    ];

    public function __construct(
        private NotifyPatient $notifyPatient,
        private RecordCaseActivity $recordActivity,
        private TreatmentProposalTransitionMap $proposalTransitions,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $failedTeams = 0;

        Team::query()
            ->with('organizationSetting')
            ->orderBy('id')
            ->each(function (Team $team) use (&$failedTeams): void {
                if ($team->organizationSetting?->isSuspended()) {
                    return;
                }

                if (! $this->isLocalHour($team)) {
                    return;
                }

                try {
                    $this->processTeam($team);
                } catch (Throwable $exception) {
                    $failedTeams++;
                    Log::error('Daily reminders failed for an organization', [
                        'team_id' => $team->id,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);
                    $this->error(sprintf('Could not finish reminders for %s: %s', $team->name, $exception->getMessage()));
                }
            });

        $this->info(sprintf(
            'Follow-up emails: %d · Patient reminder drafts: %d · Overdue task emails: %d · Proposals expired: %d',
            $this->totals['follow_up_emails'],
            $this->totals['patient_drafts'],
            $this->totals['task_digests'],
            $this->totals['expired_proposals'],
        ));

        return $failedTeams === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The scheduler runs this every hour with --local-hour=7, so each hospital gets
     * its reminders at 7 in the morning in its own time zone.
     */
    private function isLocalHour(Team $team): bool
    {
        $hour = $this->option('local-hour');

        if ($hour === null || $hour === '') {
            return true;
        }

        $timezone = $team->organizationSetting?->timezone ?: config('app.timezone');

        return now($timezone)->hour === (int) $hour;
    }

    private function processTeam(Team $team): void
    {
        $timezone = $team->organizationSetting?->timezone ?: config('app.timezone');
        $startOfToday = now($timezone)->startOfDay()->setTimezone(config('app.timezone'));
        $endOfToday = now($timezone)->endOfDay()->setTimezone(config('app.timezone'));

        $this->remindFollowUps($team, $startOfToday, $endOfToday);
        $this->remindOverdueTasks($team, $startOfToday);
        $this->expireProposals($team, now($timezone)->toDateString());
    }

    private function remindFollowUps(Team $team, CarbonInterface $startOfToday, CarbonInterface $endOfToday): void
    {
        $emailStaff = $team->notificationEnabled('staff_daily_reminders');
        $draftForPatient = $team->notificationEnabled('patient_follow_up_reminders');

        if (! $emailStaff && ! $draftForPatient) {
            return;
        }

        /** @var Collection<int, PatientFollowUp> $followUps */
        $followUps = PatientFollowUp::query()
            ->forTeam($team)
            ->whereIn('status', [FollowUpStatus::Scheduled, FollowUpStatus::InProgress])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $endOfToday)
            ->where(fn (Builder $query) => $query
                ->whereNull('reminded_at')
                ->orWhere('reminded_at', '<', $startOfToday))
            ->whereHas('case')
            ->with(['case.patient', 'case.assignedCoordinator'])
            ->orderBy('due_at')
            ->get();

        if ($followUps->isEmpty()) {
            return;
        }

        if ($emailStaff) {
            $this->totals['follow_up_emails'] += $this->emailGroupedByRecipient(
                $team,
                $followUps,
                fn (PatientFollowUp $followUp): ?int => $followUp->assigned_user_id ?? $followUp->case->assigned_coordinator_id,
                fn (Collection $items): FollowUpsDueNotification => new FollowUpsDueNotification($team, $items),
            );
        }

        if ($draftForPatient) {
            $followUps
                ->filter(fn (PatientFollowUp $followUp): bool => $followUp->reminded_at === null
                    && $followUp->due_at->gte($startOfToday)
                    && in_array($followUp->follow_up_type, self::PATIENT_FACING_FOLLOW_UP_TYPES, true))
                ->each(function (PatientFollowUp $followUp): void {
                    $coordinator = $followUp->case->assignedCoordinator;

                    if ($coordinator === null) {
                        return;
                    }

                    $draft = $this->notifyPatient->handle(
                        case: $followUp->case,
                        key: MessageTemplateKey::FollowUpReminder,
                        actor: $coordinator,
                        sendNow: false,
                        details: $followUp->title,
                    );

                    if ($draft !== null) {
                        $this->totals['patient_drafts']++;
                    }
                });
        }

        PatientFollowUp::query()
            ->whereKey($followUps->modelKeys())
            ->update(['reminded_at' => now()]);
    }

    private function remindOverdueTasks(Team $team, CarbonInterface $startOfToday): void
    {
        if (! $team->notificationEnabled('staff_daily_reminders')) {
            return;
        }

        /** @var Collection<int, CaseTask> $tasks */
        $tasks = CaseTask::query()
            ->forTeam($team)
            ->whereIn('status', [CaseTaskStatus::Open, CaseTaskStatus::InProgress, CaseTaskStatus::Waiting])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where(fn (Builder $query) => $query
                ->whereNull('last_overdue_reminder_at')
                ->orWhere('last_overdue_reminder_at', '<', $startOfToday))
            ->whereHas('case')
            ->with('case.patient')
            ->orderBy('due_at')
            ->get();

        if ($tasks->isEmpty()) {
            return;
        }

        $this->totals['task_digests'] += $this->emailGroupedByRecipient(
            $team,
            $tasks,
            fn (CaseTask $task): ?int => $task->assigned_user_id ?? $task->case->assigned_coordinator_id,
            fn (Collection $items): OverdueTasksDigestNotification => new OverdueTasksDigestNotification($team, $items),
        );

        CaseTask::query()
            ->whereKey($tasks->modelKeys())
            ->update(['last_overdue_reminder_at' => now()]);
    }

    private function expireProposals(Team $team, string $today): void
    {
        TreatmentProposal::query()
            ->forTeam($team)
            ->where('status', TreatmentProposalStatus::SentToPatient)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', $today)
            ->whereHas('case')
            ->pluck('id')
            ->each(function (int $proposalId) use ($team): void {
                $expired = DB::transaction(function () use ($proposalId): ?TreatmentProposal {
                    /** @var TreatmentProposal|null $proposal */
                    $proposal = TreatmentProposal::query()->whereKey($proposalId)->lockForUpdate()->first();

                    if ($proposal === null || ! $this->proposalTransitions->canTransition($proposal->status, TreatmentProposalStatus::Expired)) {
                        return null;
                    }

                    $proposal->update(['status' => TreatmentProposalStatus::Expired]);

                    $this->recordActivity->handle(
                        case: $proposal->case,
                        type: CaseActivityType::TreatmentProposalExpired,
                        summary: sprintf(
                            'Treatment proposal %s expired because the patient did not answer before %s',
                            $proposal->reference,
                            $proposal->valid_until?->toFormattedDateString(),
                        ),
                        properties: [
                            'proposal_uuid' => $proposal->uuid,
                            'proposal_reference' => $proposal->reference,
                            'valid_until' => $proposal->valid_until?->toDateString(),
                        ],
                    );

                    return $proposal;
                });

                if ($expired === null) {
                    return;
                }

                $this->totals['expired_proposals']++;

                $case = $expired->case()->with('assignedCoordinator')->first();
                $coordinator = $case?->assignedCoordinator;

                if ($coordinator !== null && $team->notificationEnabled('staff_proposal_expired') && $this->isMember($team, $coordinator->id)) {
                    $coordinator->notify(new TreatmentProposalExpiredNotification($case, $expired));
                }
            });
    }

    /**
     * Send one email per staff member covering all of their items. Items without anyone
     * responsible, or whose person has left the organization, are skipped.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $items
     * @param  callable(TModel): ?int  $recipientId
     * @param  callable(Collection<int, TModel>): Notification  $makeNotification
     * @return int The number of emails sent.
     */
    private function emailGroupedByRecipient(Team $team, Collection $items, callable $recipientId, callable $makeNotification): int
    {
        $groups = $items
            ->filter(fn ($item): bool => $recipientId($item) !== null)
            ->groupBy(fn ($item): int => $recipientId($item));

        if ($groups->isEmpty()) {
            return 0;
        }

        $recipients = $team->members()
            ->whereIn('users.id', $groups->keys()->all())
            ->get()
            ->keyBy('id');

        $sent = 0;

        foreach ($groups as $userId => $group) {
            /** @var User|null $recipient */
            $recipient = $recipients->get($userId);

            if ($recipient === null) {
                continue;
            }

            $recipient->notify($makeNotification(new Collection($group->all())));
            $sent++;
        }

        return $sent;
    }

    private function isMember(Team $team, int $userId): bool
    {
        return $team->members()->where('users.id', $userId)->exists();
    }
}
