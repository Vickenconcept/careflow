<?php

use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\CommunicationStatus;
use App\Enums\FollowUpStatus;
use App\Enums\FollowUpType;
use App\Enums\TeamRole;
use App\Enums\TreatmentProposalStatus;
use App\Models\CaseActivity;
use App\Models\CaseCommunication;
use App\Models\CaseTask;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\PatientFollowUp;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;
use App\Notifications\Patients\FollowUpsDueNotification;
use App\Notifications\Patients\OverdueTasksDigestNotification;
use App\Notifications\Patients\TreatmentProposalExpiredNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 11, 13)->setTime(7, 0));
});

/**
 * @return array{0: User, 1: Team, 2: PatientCase}
 */
function reminderCase(): array
{
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting()->update(['timezone' => 'UTC']);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);

    $patient = Patient::factory()->create(['team_id' => $team->id, 'email' => 'patient@example.test', 'preferred_language' => 'en']);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id])->id,
        'assigned_coordinator_id' => $coordinator->id,
        'status' => CaseStatus::FollowUp,
    ]);

    return [$coordinator, $team, $case];
}

test('follow-ups due today email staff once and prepare one patient reminder draft', function () {
    [$coordinator, $team, $case] = reminderCase();

    $dueToday = PatientFollowUp::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'follow_up_type' => FollowUpType::ClinicalCheckIn,
        'status' => FollowUpStatus::Scheduled,
        'due_at' => now()->setTime(15, 0),
    ]);
    $overdue = PatientFollowUp::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'follow_up_type' => FollowUpType::OutcomeReview,
        'status' => FollowUpStatus::InProgress,
        'due_at' => now()->subDays(3),
    ]);
    PatientFollowUp::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'follow_up_type' => FollowUpType::Administrative,
        'due_at' => now()->setTime(11, 0),
    ]);

    $this->artisan('careflow:daily-reminders')->assertSuccessful();
    $this->artisan('careflow:daily-reminders')->assertSuccessful();

    Notification::assertSentToTimes($coordinator, FollowUpsDueNotification::class, 1);
    Notification::assertSentTo($coordinator, FollowUpsDueNotification::class, fn (FollowUpsDueNotification $notification) => $notification->followUps->count() === 3);

    $drafts = CaseCommunication::query()->where('patient_case_id', $case->id)->get();

    expect($drafts)->toHaveCount(1)
        ->and($drafts->first()->status)->toBe(CommunicationStatus::AwaitingApproval)
        ->and($dueToday->fresh()->reminded_at)->not->toBeNull()
        ->and($overdue->fresh()->reminded_at)->not->toBeNull();
});

test('follow-up reminders go to the assigned staff member and repeat the next day without another patient draft', function () {
    [$coordinator, $team, $case] = reminderCase();
    $nurse = User::factory()->create();
    $team->members()->attach($nurse, ['role' => TeamRole::Coordinator->value]);

    PatientFollowUp::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'assigned_user_id' => $nurse->id,
        'follow_up_type' => FollowUpType::ClinicalCheckIn,
        'due_at' => now()->setTime(9, 0),
    ]);

    $this->artisan('careflow:daily-reminders')->assertSuccessful();

    $this->travel(1)->days();
    $this->artisan('careflow:daily-reminders')->assertSuccessful();

    Notification::assertSentToTimes($nurse, FollowUpsDueNotification::class, 2);
    Notification::assertNotSentTo($coordinator, FollowUpsDueNotification::class);

    expect(CaseCommunication::query()->where('patient_case_id', $case->id)->count())->toBe(1);
});

test('overdue tasks are sent as one digest per person per day', function () {
    [$coordinator, $team, $case] = reminderCase();

    $tasks = CaseTask::factory()->count(2)->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'assigned_user_id' => $coordinator->id,
        'status' => CaseTaskStatus::Open,
        'due_at' => now()->subDay(),
    ]);
    CaseTask::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'assigned_user_id' => $coordinator->id,
        'status' => CaseTaskStatus::Completed,
        'due_at' => now()->subDay(),
    ]);

    $this->artisan('careflow:daily-reminders')->assertSuccessful();
    $this->artisan('careflow:daily-reminders')->assertSuccessful();

    Notification::assertSentToTimes($coordinator, OverdueTasksDigestNotification::class, 1);
    Notification::assertSentTo($coordinator, OverdueTasksDigestNotification::class, function (OverdueTasksDigestNotification $notification) use ($coordinator, $case) {
        $html = (string) $notification->toMail($coordinator)->render();

        return $notification->tasks->count() === 2
            && str_contains($html, 'tab=tasks')
            && str_contains($html, e($case->reference));
    });

    expect($tasks->first()->fresh()->last_overdue_reminder_at)->not->toBeNull();

    $this->travel(1)->days();
    $this->artisan('careflow:daily-reminders')->assertSuccessful();

    Notification::assertSentToTimes($coordinator, OverdueTasksDigestNotification::class, 2);
});

test('proposals past their valid-until date expire and the coordinator is told', function () {
    [$coordinator, $team, $case] = reminderCase();

    $proposal = TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::SentToPatient,
        'sent_at' => now()->subMonth(),
        'valid_until' => now()->subDay()->toDateString(),
        'is_current' => true,
    ]);

    $this->artisan('careflow:daily-reminders')->assertSuccessful();
    $this->artisan('careflow:daily-reminders')->assertSuccessful();

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::Expired)
        ->and(CaseActivity::query()
            ->where('patient_case_id', $case->id)
            ->where('activity_type', CaseActivityType::TreatmentProposalExpired)
            ->count())->toBe(1);

    Notification::assertSentToTimes($coordinator, TreatmentProposalExpiredNotification::class, 1);
});

test('nothing happens for dates in the future', function () {
    [$coordinator, $team, $case] = reminderCase();

    $followUp = PatientFollowUp::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'follow_up_type' => FollowUpType::ClinicalCheckIn,
        'due_at' => now()->addDay(),
    ]);
    CaseTask::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'assigned_user_id' => $coordinator->id,
        'due_at' => now()->addHour(),
    ]);
    $proposal = TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::SentToPatient,
        'valid_until' => now()->toDateString(),
    ]);

    $this->artisan('careflow:daily-reminders')->assertSuccessful();

    Notification::assertNothingSent();

    expect($followUp->fresh()->reminded_at)->toBeNull()
        ->and($proposal->fresh()->status)->toBe(TreatmentProposalStatus::SentToPatient)
        ->and(CaseCommunication::query()->where('patient_case_id', $case->id)->exists())->toBeFalse();
});

test('organizations that switch reminders off get no reminders', function () {
    [$coordinator, $team, $case] = reminderCase();
    $team->organizationSetting->update(['notification_settings' => [
        'staff_daily_reminders' => false,
        'patient_follow_up_reminders' => false,
        'staff_proposal_expired' => false,
    ]]);

    PatientFollowUp::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'follow_up_type' => FollowUpType::ClinicalCheckIn,
        'due_at' => now()->setTime(12, 0),
    ]);
    CaseTask::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'assigned_user_id' => $coordinator->id,
        'due_at' => now()->subDay(),
    ]);
    $proposal = TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::SentToPatient,
        'valid_until' => now()->subDays(2)->toDateString(),
    ]);

    $this->artisan('careflow:daily-reminders')->assertSuccessful();

    Notification::assertNothingSent();

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::Expired)
        ->and(CaseCommunication::query()->where('patient_case_id', $case->id)->exists())->toBeFalse();
});

test('moving a follow-up to a new date lets it be reminded again on that date', function () {
    [$coordinator, $team, $case] = reminderCase();
    $coordinator->switchTeam($team);

    $followUp = PatientFollowUp::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'follow_up_type' => FollowUpType::ClinicalCheckIn,
        'status' => FollowUpStatus::Scheduled,
        'due_at' => now()->setTime(15, 0),
    ]);

    $this->artisan('careflow:daily-reminders')->assertSuccessful();
    expect($followUp->fresh()->reminded_at)->not->toBeNull();

    $this->actingAs($coordinator)
        ->patch(route('organizations.follow-ups.update', ['current_team' => $team, 'case' => $case, 'followUp' => $followUp]), [
            'due_at' => now()->addDays(2)->setTime(10, 0)->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    expect($followUp->fresh()->reminded_at)->toBeNull();

    $this->travel(2)->days();
    $this->artisan('careflow:daily-reminders')->assertSuccessful();

    Notification::assertSentToTimes($coordinator, FollowUpsDueNotification::class, 2);
    expect(CaseCommunication::query()->where('patient_case_id', $case->id)->count())->toBe(2);
});

test('the hourly run only handles hospitals where it is 7 in the morning', function () {
    [$coordinator, $team, $case] = reminderCase();
    CaseTask::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'assigned_user_id' => $coordinator->id,
        'status' => CaseTaskStatus::Open,
        'due_at' => now()->subDay(),
    ]);

    // It is 07:00 UTC; a hospital in Istanbul (UTC+3) is at 10:00 and is skipped.
    $team->organizationSetting->update(['timezone' => 'Europe/Istanbul']);
    $this->artisan('careflow:daily-reminders', ['--local-hour' => 7])->assertSuccessful();
    Notification::assertNothingSentTo($coordinator);

    // Back on UTC it is 07:00 there, so it runs.
    $team->organizationSetting->update(['timezone' => 'UTC']);
    $this->artisan('careflow:daily-reminders', ['--local-hour' => 7])->assertSuccessful();
    Notification::assertSentToTimes($coordinator, OverdueTasksDigestNotification::class, 1);
});
