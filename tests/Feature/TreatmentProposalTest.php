<?php

use App\Enums\CaseStatus;
use App\Enums\ClinicalRecommendationStatus;
use App\Enums\TeamRole;
use App\Enums\TreatmentProposalStatus;
use App\Models\ClinicalRecommendation;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;

function proposalCase(Team $team, array $overrides = []): PatientCase
{
    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);

    return PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'status' => CaseStatus::TreatmentProposalPreparation,
        'reference' => 'CF-2026-'.fake()->unique()->numerify('91####'),
        ...$overrides,
    ]);
}

test('coordinators can create and submit treatment proposals for clinical approval', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $coordinator->switchTeam($team);

    $case = proposalCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
        'assigned_doctor_id' => $doctor->id,
        'reference' => 'CF-2026-910001',
    ]);

    $recommendation = ClinicalRecommendation::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'author_user_id' => $doctor->id,
        'status' => ClinicalRecommendationStatus::Approved,
        'is_ai_generated' => false,
        'is_current' => true,
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'proposed_procedure' => 'Outpatient cardiology package',
            'estimated_cost' => 2400,
            'currency' => 'EUR',
            'expected_duration' => '3 days',
            'required_appointments' => 2,
            'clinical_recommendation_id' => $recommendation->id,
            'responsible_physician_id' => $doctor->id,
            'items' => [
                [
                    'description' => 'Specialist consultation',
                    'quantity' => 1,
                    'unit_cost' => 400,
                ],
            ],
        ])
        ->assertRedirect();

    $proposal = TreatmentProposal::query()->where('patient_case_id', $case->id)->firstOrFail();

    expect($proposal->status)->toBe(TreatmentProposalStatus::Draft)
        ->and($proposal->reference)->toStartWith('TP-')
        ->and($proposal->items)->toHaveCount(1);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.transition', [
            'current_team' => $team,
            'case' => $case,
            'proposal' => $proposal,
        ]), [
            'action' => 'submit_for_approval',
        ])
        ->assertRedirect();

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::AwaitingClinicalApproval);
});

test('doctors can approve proposals and coordinators can send them', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);
    $coordinator->switchTeam($team);

    $case = proposalCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
        'assigned_doctor_id' => $doctor->id,
        'reference' => 'CF-2026-910002',
    ]);

    $proposal = TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'responsible_physician_id' => $doctor->id,
        'status' => TreatmentProposalStatus::AwaitingClinicalApproval,
        'proposed_procedure' => 'Demo package',
        'is_current' => true,
    ]);

    $this->actingAs($doctor)
        ->post(route('organizations.proposals.transition', [
            'current_team' => $team,
            'case' => $case,
            'proposal' => $proposal,
        ]), [
            'action' => 'approve',
            'notes' => 'Approved for administrative send',
        ])
        ->assertRedirect();

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::Approved)
        ->and($proposal->fresh()->approved_by_user_id)->toBe($doctor->id);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.transition', [
            'current_team' => $team,
            'case' => $case,
            'proposal' => $proposal,
        ]), [
            'action' => 'send',
        ])
        ->assertRedirect();

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::SentToPatient)
        ->and($case->fresh()->status)->toBe(CaseStatus::ProposalSent);
});

test('coordinators cannot clinically approve treatment proposals', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $case = proposalCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
        'reference' => 'CF-2026-910003',
    ]);

    $proposal = TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::AwaitingClinicalApproval,
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.transition', [
            'current_team' => $team,
            'case' => $case,
            'proposal' => $proposal,
        ]), [
            'action' => 'approve',
        ])
        ->assertForbidden();
});

test('proposals cannot link unapproved or ai clinical recommendations', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = $owner->currentTeam;
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $case = proposalCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
        'reference' => 'CF-2026-910004',
    ]);

    $recommendation = ClinicalRecommendation::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'author_user_id' => $owner->id,
        'status' => ClinicalRecommendationStatus::Draft,
        'is_ai_generated' => false,
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'proposed_procedure' => 'Should fail linkage',
            'clinical_recommendation_id' => $recommendation->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('proposed_procedure');
});

test('patient acceptance moves case toward appointment planning path', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $coordinator->switchTeam($team);

    $case = proposalCase($team, [
        'status' => CaseStatus::ProposalSent,
        'assigned_coordinator_id' => $coordinator->id,
        'assigned_doctor_id' => $doctor->id,
        'reference' => 'CF-2026-910005',
    ]);

    $proposal = TreatmentProposal::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'approved_by_user_id' => $doctor->id,
        'approved_at' => now()->subHour(),
        'sent_at' => now()->subMinutes(30),
        'sent_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::SentToPatient,
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.transition', [
            'current_team' => $team,
            'case' => $case,
            'proposal' => $proposal,
        ]), [
            'action' => 'record_patient_response',
            'patient_response' => 'accepted',
        ])
        ->assertRedirect();

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::PatientAccepted)
        ->and($case->fresh()->status)->toBe(CaseStatus::AwaitingPatientResponse);
});
