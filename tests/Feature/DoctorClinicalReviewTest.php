<?php

use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Enums\ClinicalApprovalOutcome;
use App\Enums\ClinicalRecommendationStatus;
use App\Enums\DoctorReviewOutcome;
use App\Enums\DoctorReviewStatus;
use App\Enums\TeamRole;
use App\Models\ClinicalRecommendation;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function clinicalCase(Team $team, array $overrides = []): PatientCase
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
        'status' => CaseStatus::AwaitingDoctorReview,
        'reference' => 'CF-2026-'.fake()->unique()->numerify('8#####'),
        ...$overrides,
    ]);
}

test('assigned doctors can record observations and submit a human review', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    $case = clinicalCase($team, [
        'assigned_doctor_id' => $doctor->id,
        'reference' => 'CF-2026-800001',
    ]);

    $this->actingAs($doctor)
        ->post(route('organizations.clinical.observations.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'body' => 'Human observation: ECG strip is incomplete.',
            'visibility' => 'coordinator_shared',
        ])
        ->assertRedirect(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case->reference,
        ]));

    $this->actingAs($doctor)
        ->post(route('organizations.clinical.reviews.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'outcome' => DoctorReviewOutcome::ReadyForProposal->value,
            'findings' => 'Human-reviewed evaluation. Recommend cardiology proposal preparation.',
        ])
        ->assertRedirect();

    $case->refresh();

    expect($case->status)->toBe(CaseStatus::TreatmentProposalPreparation)
        ->and($case->doctorReviews()->count())->toBe(1)
        ->and($case->doctorReviews()->first()->status)->toBe(DoctorReviewStatus::Completed)
        ->and($case->doctorReviews()->first()->findings)->toContain('Human-reviewed evaluation');

    $this->assertDatabaseHas('case_activities', [
        'patient_case_id' => $case->id,
        'activity_type' => CaseActivityType::DoctorReviewSubmitted->value,
        'actor_user_id' => $doctor->id,
    ]);
});

test('doctor review can request additional information and move case status', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    $case = clinicalCase($team, [
        'assigned_doctor_id' => $doctor->id,
        'reference' => 'CF-2026-800002',
    ]);

    $this->actingAs($doctor)
        ->post(route('organizations.clinical.reviews.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'outcome' => DoctorReviewOutcome::NeedMoreInformation->value,
            'findings' => 'Cannot complete evaluation without labs.',
            'additional_information_request' => 'Please upload recent bloodwork and ECG.',
        ])
        ->assertRedirect();

    expect($case->fresh()->status)->toBe(CaseStatus::AdditionalInformationRequired)
        ->and($case->fresh()->doctorReviews()->first()->status)
        ->toBe(DoctorReviewStatus::AdditionalInfoRequested);
});

test('doctors can draft and submit recommendations for clinical approval', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    $case = clinicalCase($team, [
        'assigned_doctor_id' => $doctor->id,
        'reference' => 'CF-2026-800003',
    ]);

    $this->actingAs($doctor)
        ->post(route('organizations.clinical.recommendations.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'recommendation_text' => 'Consider outpatient cardiology assessment.',
            'submit_for_approval' => true,
        ])
        ->assertRedirect();

    $recommendation = ClinicalRecommendation::query()
        ->where('patient_case_id', $case->id)
        ->where('is_current', true)
        ->firstOrFail();

    expect($recommendation->status)->toBe(ClinicalRecommendationStatus::AwaitingApproval)
        ->and($recommendation->is_ai_generated)->toBeFalse();

    $this->actingAs($doctor)
        ->post(route('organizations.clinical.approvals.store', [
            'current_team' => $team,
            'case' => $case,
            'recommendation' => $recommendation,
        ]), [
            'outcome' => ClinicalApprovalOutcome::Approved->value,
            'notes' => 'Reviewed against source documents.',
        ])
        ->assertRedirect();

    expect($recommendation->fresh()->status)->toBe(ClinicalRecommendationStatus::Approved)
        ->and($recommendation->approvals()->count())->toBe(1);
});

test('nurses can add observations but cannot submit doctor recommendations', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $nurse = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $team->members()->attach($nurse, ['role' => TeamRole::Nurse->value]);
    $nurse->switchTeam($team);

    $case = clinicalCase($team, [
        'assigned_doctor_id' => $doctor->id,
        'reference' => 'CF-2026-800007',
    ]);

    $this->actingAs($nurse)
        ->post(route('organizations.clinical.observations.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'body' => 'Nurse observation for follow-up.',
            'visibility' => 'internal',
        ])
        ->assertForbidden();

    // Nurses only see cases they are linked to via doctor or coordinator assignment.
    $case->update(['assigned_doctor_id' => $nurse->id]);

    $this->actingAs($nurse)
        ->post(route('organizations.clinical.observations.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'body' => 'Nurse observation for follow-up.',
            'visibility' => 'internal',
        ])
        ->assertRedirect();

    $this->actingAs($nurse)
        ->post(route('organizations.clinical.recommendations.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'recommendation_text' => 'Nurse should not author recommendations.',
            'submit_for_approval' => true,
        ])
        ->assertForbidden();
});

test('coordinators cannot record clinical reviews or approve recommendations', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $coordinator->switchTeam($team);

    $case = clinicalCase($team, [
        'assigned_doctor_id' => $doctor->id,
        'assigned_coordinator_id' => $coordinator->id,
        'reference' => 'CF-2026-800004',
    ]);

    $recommendation = ClinicalRecommendation::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'author_user_id' => $doctor->id,
        'status' => ClinicalRecommendationStatus::AwaitingApproval,
        'is_ai_generated' => false,
        'is_current' => true,
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.clinical.observations.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'body' => 'Coordinator should not write clinical notes.',
            'visibility' => 'internal',
        ])
        ->assertForbidden();

    $this->actingAs($coordinator)
        ->post(route('organizations.clinical.approvals.store', [
            'current_team' => $team,
            'case' => $case,
            'recommendation' => $recommendation,
        ]), [
            'outcome' => ClinicalApprovalOutcome::Approved->value,
        ])
        ->assertForbidden();
});

test('unassigned doctors cannot open clinical review for another doctor case', function () {
    $owner = User::factory()->create();
    $assignedDoctor = User::factory()->create();
    $otherDoctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($assignedDoctor, ['role' => TeamRole::Doctor->value]);
    $team->members()->attach($otherDoctor, ['role' => TeamRole::Doctor->value]);
    $otherDoctor->switchTeam($team);

    $case = clinicalCase($team, [
        'assigned_doctor_id' => $assignedDoctor->id,
        'reference' => 'CF-2026-800005',
    ]);

    $this->actingAs($otherDoctor)
        ->get(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]))
        ->assertForbidden();
});

test('case workspace exposes clinical review payloads to authorized clinicians', function () {
    $owner = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    $case = clinicalCase($team, [
        'assigned_doctor_id' => $doctor->id,
        'reference' => 'CF-2026-800006',
    ]);

    $this->actingAs($doctor)
        ->get(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/cases/show')
            ->where('permissions.canReviewClinical', true)
            ->where('permissions.canApproveClinical', true)
            ->where('case.clinical.disclaimer', 'Clinical evaluations and approvals are human-authored. AI document outputs are never clinically approved content.'));
});

test('a saved draft recommendation can be edited and sent for approval later', function () {
    $doctor = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $doctor->switchTeam($team);

    $case = clinicalCase($team, ['assigned_doctor_id' => $doctor->id]);
    $recommendation = ClinicalRecommendation::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'author_user_id' => $doctor->id,
        'status' => ClinicalRecommendationStatus::Draft,
        'recommendation_text' => 'First draft',
        'is_ai_generated' => false,
        'is_current' => true,
    ]);

    $this->actingAs($doctor)
        ->patch(route('organizations.clinical.recommendations.update', [
            'current_team' => $team,
            'case' => $case,
            'recommendation' => $recommendation,
        ]), [
            'recommendation_text' => 'Revised recommendation',
            'submit_for_approval' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($recommendation->fresh()->recommendation_text)->toBe('Revised recommendation')
        ->and($recommendation->fresh()->status)->toBe(ClinicalRecommendationStatus::AwaitingApproval);
});
