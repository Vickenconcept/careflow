<?php

use App\Enums\AppointmentStatus;
use App\Enums\CaseStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\TeamRole;
use App\Enums\TreatmentProposalStatus;
use App\Models\CaseAppointment;
use App\Models\CaseTask;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

/**
 * @return array{0: Team, 1: User, 2: PatientCase}
 */
function editableCase(CaseStatus $status = CaseStatus::ProposalSent): array
{
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();
    $coordinator = User::factory()->create();
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id])->id,
        'assigned_coordinator_id' => $coordinator->id,
        'status' => $status,
    ]);

    return [$team, $coordinator, $case];
}

test('a revised proposal replaces the current one instead of sitting beside it', function () {
    [$team, $coordinator, $case] = editableCase();
    $declined = TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::PatientDeclined,
        'is_current' => true,
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.store', ['current_team' => $team, 'case' => $case]), [
            'proposed_procedure' => 'Revised package',
            'currency' => 'EUR',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $current = TreatmentProposal::query()->where('patient_case_id', $case->id)->where('is_current', true)->get();

    expect($current)->toHaveCount(1)
        ->and($current->first()->proposed_procedure)->toBe('Revised package')
        ->and($current->first()->parent_proposal_id)->toBe($declined->id)
        ->and($declined->fresh()->status)->toBe(TreatmentProposalStatus::PatientDeclined)
        ->and($declined->fresh()->is_current)->toBeFalse();
});

test('an accepted proposal cannot be replaced', function () {
    [$team, $coordinator, $case] = editableCase(CaseStatus::AwaitingPatientResponse);
    TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::PatientAccepted,
        'is_current' => true,
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.store', ['current_team' => $team, 'case' => $case]), [
            'proposed_procedure' => 'Another package',
        ])
        ->assertSessionHasErrors();

    expect(TreatmentProposal::query()->where('patient_case_id', $case->id)->count())->toBe(1);
});

test('a confirmed appointment can be rescheduled and later marked completed', function () {
    [$team, $coordinator, $case] = editableCase(CaseStatus::AppointmentPlanning);
    $appointment = CaseAppointment::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => AppointmentStatus::Confirmed,
        'hospital_slot_verified' => true,
        'confirmed_starts_at' => now()->addDays(3),
        'proposed_starts_at' => now()->addDays(3),
    ]);
    $route = ['current_team' => $team, 'case' => $case, 'appointment' => $appointment];

    $this->actingAs($coordinator)
        ->post(route('organizations.appointments.transition', $route), [
            'action' => 'reschedule',
            'proposed_starts_at' => now()->addDays(10)->setTime(9, 30)->toDateTimeString(),
        ])
        ->assertSessionHasNoErrors();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Rescheduled)
        ->and($appointment->fresh()->confirmed_starts_at)->toBeNull();

    $this->actingAs($coordinator)
        ->post(route('organizations.appointments.transition', $route), [
            'action' => 'confirm',
            'hospital_slot_verified' => true,
            'confirmation_source' => 'manual_hospital_verified',
            'confirmed_starts_at' => now()->addDays(10)->setTime(9, 30)->toDateTimeString(),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($coordinator)
        ->post(route('organizations.appointments.transition', $route), ['action' => 'complete'])
        ->assertSessionHasNoErrors();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed);
});

test('tasks can be reassigned, given a new due date and cancelled', function () {
    [$team, $coordinator, $case] = editableCase();
    $colleague = User::factory()->create();
    $team->members()->attach($colleague, ['role' => TeamRole::Coordinator->value]);
    $task = CaseTask::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'assigned_user_id' => $coordinator->id,
        'status' => CaseTaskStatus::Open,
    ]);
    $route = ['current_team' => $team, 'case' => $case, 'task' => $task];

    $this->actingAs($coordinator)
        ->patch(route('organizations.tasks.update', $route), [
            'title' => 'Call the hotel',
            'assigned_user_id' => $colleague->id,
            'due_at' => now()->addDays(2)->toDateTimeString(),
            'priority' => 'high',
        ])
        ->assertSessionHasNoErrors();

    expect($task->fresh()->title)->toBe('Call the hotel')
        ->and($task->fresh()->assigned_user_id)->toBe($colleague->id);

    $this->actingAs($coordinator)
        ->post(route('organizations.tasks.transition', $route), ['status' => 'cancelled'])
        ->assertSessionHasNoErrors();

    expect($task->fresh()->status)->toBe(CaseTaskStatus::Cancelled);
});

test('a due date sent as a UTC ISO string from the browser is stored as that moment', function () {
    [$team, $coordinator, $case] = editableCase();
    $task = CaseTask::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'assigned_user_id' => $coordinator->id,
        'status' => CaseTaskStatus::Open,
    ]);

    $this->actingAs($coordinator)
        ->patch(route('organizations.tasks.update', ['current_team' => $team, 'case' => $case, 'task' => $task]), [
            'due_at' => '2026-12-01T06:30:00.000Z',
        ])
        ->assertSessionHasNoErrors();

    expect($task->fresh()->due_at->utc()->toDateTimeString())->toBe('2026-12-01 06:30:00');
});
