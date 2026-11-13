<?php

use App\Enums\CaseStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
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
use Inertia\Testing\AssertableInertia as Assert;

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

/**
 * @return array{0: User, 1: Team, 2: PatientCase, 3: Patient}
 */
function conversationCase(): array
{
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $patient = Patient::factory()->create(['team_id' => $team->id, 'email' => 'conversation@example.test']);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id])->id,
        'assigned_coordinator_id' => $coordinator->id,
        'status' => CaseStatus::AwaitingPatientResponse,
    ]);

    return [$coordinator, $team, $case, $patient];
}

test('sending a message straight away emails the patient a working link to reply', function () {
    Mail::fake();
    [$coordinator, $team, $case, $patient] = conversationCase();

    $this->actingAs($coordinator)
        ->post(route('organizations.communications.store', ['current_team' => $team, 'case' => $case]), [
            'channel' => CommunicationChannel::Email->value,
            'subject' => 'Your scan',
            'body' => 'We received your scan.',
            'send_now' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $message = CaseCommunication::query()->where('patient_case_id', $case->id)->firstOrFail();
    expect($message->status)->toBe(CommunicationStatus::Sent)
        ->and($message->recipient)->toBe($patient->email);

    Mail::assertSent(CaseCommunicationMail::class, function (CaseCommunicationMail $mail) use ($case, $patient) {
        parse_str((string) parse_url((string) $mail->portalUrl, PHP_URL_QUERY), $query);

        return $mail->hasTo($patient->email)
            && str_contains((string) $mail->portalUrl, $case->reference)
            && $case->fresh()->portalAccessTokenIsValid($query['token'] ?? '');
    });
});

test('drafts can be edited or discarded before they are sent', function () {
    [$coordinator, $team, $case] = conversationCase();
    $draft = CaseCommunication::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'channel' => CommunicationChannel::Email,
        'status' => CommunicationStatus::AwaitingApproval,
        'body' => 'Old wording',
    ]);
    $route = ['current_team' => $team, 'case' => $case, 'communication' => $draft];

    $this->actingAs($coordinator)
        ->patch(route('organizations.communications.update', $route), ['subject' => 'Update', 'body' => 'New wording'])
        ->assertRedirect();
    expect($draft->fresh()->body)->toBe('New wording');

    $this->actingAs($coordinator)
        ->delete(route('organizations.communications.destroy', $route))
        ->assertRedirect();
    expect(CaseCommunication::query()->whereKey($draft->id)->exists())->toBeFalse();
});

test('sent messages cannot be discarded', function () {
    [$coordinator, $team, $case] = conversationCase();
    $sent = CaseCommunication::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'channel' => CommunicationChannel::Email,
        'status' => CommunicationStatus::Sent,
    ]);

    $this->actingAs($coordinator)
        ->delete(route('organizations.communications.destroy', ['current_team' => $team, 'case' => $case, 'communication' => $sent]))
        ->assertSessionHasErrors('communication');

    expect(CaseCommunication::query()->whereKey($sent->id)->exists())->toBeTrue();
});

test('the patient portal shows emails that were sent but not unsent drafts', function () {
    [, $team, $case] = conversationCase();
    CaseCommunication::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'channel' => CommunicationChannel::Email,
        'direction' => CommunicationDirection::Outbound,
        'status' => CommunicationStatus::Sent,
        'body' => 'Sent email body',
    ]);
    CaseCommunication::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'channel' => CommunicationChannel::Email,
        'direction' => CommunicationDirection::Outbound,
        'status' => CommunicationStatus::AwaitingApproval,
        'body' => 'Unsent draft body',
    ]);
    $token = $case->issuePortalAccessToken();

    $this->get(route('portal.cases.show', ['organization' => $team->slug, 'reference' => $case->reference, 'token' => $token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/case-tracking')
            ->has('messages', 1)
            ->where('messages.0.body', 'Sent email body'));
});
