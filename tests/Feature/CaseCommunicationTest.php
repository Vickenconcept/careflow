<?php

use App\Enums\CaseStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\TeamRole;
use App\Mail\CaseCommunicationMail;
use App\Models\CaseCommunication;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('coordinators can log internal communications and approve outbound drafts', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $patient = Patient::factory()->create([
        'team_id' => $team->id,
        'email' => 'patient@example.test',
    ]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'assigned_coordinator_id' => $coordinator->id,
        'status' => CaseStatus::AwaitingPatientResponse,
        'reference' => 'CF-2026-930201',
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.communications.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'channel' => CommunicationChannel::Internal->value,
            'body' => 'Called patient and confirmed proposal review in progress.',
            'log_only' => true,
        ])
        ->assertRedirect();

    expect(CaseCommunication::query()->where('patient_case_id', $case->id)->count())->toBe(1)
        ->and(CaseCommunication::query()->first()->status)->toBe(CommunicationStatus::Logged);

    $this->actingAs($coordinator)
        ->post(route('organizations.communications.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'channel' => CommunicationChannel::Email->value,
            'body' => 'Thank you. Please reply when ready to schedule.',
            'subject' => 'Next steps',
            'recipient' => $patient->email,
            'log_only' => false,
        ])
        ->assertRedirect();

    $outbound = CaseCommunication::query()
        ->where('patient_case_id', $case->id)
        ->where('channel', CommunicationChannel::Email->value)
        ->firstOrFail();

    expect($outbound->status)->toBe(CommunicationStatus::AwaitingApproval)
        ->and($outbound->requires_approval)->toBeTrue();

    $this->actingAs($coordinator)
        ->post(route('organizations.communications.approve', [
            'current_team' => $team,
            'case' => $case,
            'communication' => $outbound,
        ]), [
            'mark_sent' => false,
        ])
        ->assertRedirect();

    expect($outbound->fresh()->status)->toBe(CommunicationStatus::Approved)
        ->and($outbound->fresh()->approved_by_user_id)->toBe($coordinator->id);
});

test('approving with mark_sent dispatches outbound email through the log provider', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $patient = Patient::factory()->create([
        'team_id' => $team->id,
        'email' => 'patient-send@example.test',
    ]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'assigned_coordinator_id' => $coordinator->id,
        'status' => CaseStatus::AwaitingPatientResponse,
        'reference' => 'CF-2026-930203',
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.communications.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'channel' => CommunicationChannel::Email->value,
            'body' => 'Your appointment options are ready for review.',
            'subject' => 'Appointment options',
            'recipient' => $patient->email,
            'log_only' => false,
        ])
        ->assertRedirect();

    $outbound = CaseCommunication::query()
        ->where('patient_case_id', $case->id)
        ->where('channel', CommunicationChannel::Email->value)
        ->firstOrFail();

    $this->actingAs($coordinator)
        ->post(route('organizations.communications.approve', [
            'current_team' => $team,
            'case' => $case,
            'communication' => $outbound,
        ]), [
            'mark_sent' => true,
        ])
        ->assertRedirect();

    expect($outbound->fresh()->status)->toBe(CommunicationStatus::Sent)
        ->and($outbound->fresh()->sent_at)->not->toBeNull()
        ->and($outbound->fresh()->provider_reference)->not->toBeNull();

    Mail::assertSent(CaseCommunicationMail::class, function (CaseCommunicationMail $mail) use ($patient) {
        return $mail->hasTo($patient->email);
    });
});

test('ai drafting is blocked when openai provider is not enabled', function () {
    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
    ]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'reference' => 'CF-2026-930202',
    ]);

    $this->actingAs($coordinator)
        ->from(route('organizations.cases.show', [
            'current_team' => $team,
            'case' => $case,
        ]))
        ->post(route('organizations.communications.store', [
            'current_team' => $team,
            'case' => $case,
        ]), [
            'channel' => CommunicationChannel::Email->value,
            'draft_with_ai' => true,
            'ai_brief' => 'Ask patient for preferred travel week',
            'log_only' => false,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('communication');
});
