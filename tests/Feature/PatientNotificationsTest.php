<?php

use App\Actions\Patients\SubmitDoctorReview;
use App\Actions\Patients\UpsertTravelArrangement;
use App\Contracts\Communications\OutboundCommunicationSender;
use App\Enums\AppointmentStatus;
use App\Enums\CaseStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Enums\DoctorReviewOutcome;
use App\Enums\TeamRole;
use App\Enums\TreatmentProposalStatus;
use App\Mail\CaseCommunicationMail;
use App\Models\CaseAppointment;
use App\Models\CaseCommunication;
use App\Models\CaseTask;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();
});

/**
 * @return array{0: User, 1: Team, 2: PatientCase}
 */
function notifiedCase(CaseStatus $status, array $patient = []): array
{
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting()->update(['timezone' => 'Europe/Istanbul']);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $coordinator->switchTeam($team);

    $patientModel = Patient::factory()->create(['team_id' => $team->id, 'email' => 'patient@example.test', 'preferred_language' => 'en', ...$patient]);
    $case = PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patientModel->id,
        'patient_enquiry_id' => PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patientModel->id])->id,
        'assigned_coordinator_id' => $coordinator->id,
        'status' => $status,
    ]);

    return [$coordinator, $team, $case];
}

function approvedProposal(PatientCase $case, User $coordinator): TreatmentProposal
{
    return TreatmentProposal::factory()->create([
        'team_id' => $case->team_id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => TreatmentProposalStatus::Approved,
        'approved_by_user_id' => $coordinator->id,
        'approved_at' => now(),
        'proposed_procedure' => 'Cardiac evaluation package',
        'valid_until' => now()->addMonth(),
        'is_current' => true,
    ]);
}

test('sending a proposal emails the patient and records it in the conversation', function () {
    [$coordinator, $team, $case] = notifiedCase(CaseStatus::TreatmentProposalPreparation);
    $proposal = approvedProposal($case, $coordinator);

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.transition', ['current_team' => $team, 'case' => $case, 'proposal' => $proposal]), ['action' => 'send'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Proposal sent. The patient was emailed.');

    $message = CaseCommunication::query()->where('patient_case_id', $case->id)->sole();

    expect($message->status)->toBe(CommunicationStatus::Sent)
        ->and($message->channel)->toBe(CommunicationChannel::Email)
        ->and($message->body)->toContain($case->reference);

    Mail::assertSent(CaseCommunicationMail::class, fn (CaseCommunicationMail $mail) => $mail->hasTo('patient@example.test') && $mail->portalUrl !== null);
});

test('a delivery problem keeps the notice as a draft without undoing the staff action', function () {
    [$coordinator, $team, $case] = notifiedCase(CaseStatus::TreatmentProposalPreparation);
    $proposal = approvedProposal($case, $coordinator);
    app()->bind(OutboundCommunicationSender::class, fn () => new class implements OutboundCommunicationSender
    {
        public function send(CaseCommunication $communication): array
        {
            throw new RuntimeException('Mail transport is down');
        }
    });

    $this->actingAs($coordinator)
        ->post(route('organizations.proposals.transition', ['current_team' => $team, 'case' => $case, 'proposal' => $proposal]), ['action' => 'send'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::SentToPatient)
        ->and(CaseCommunication::query()->where('patient_case_id', $case->id)->sole()->status)->toBe(CommunicationStatus::AwaitingApproval);
});

test('confirming an appointment emails the patient the date and place', function () {
    [$coordinator, $team, $case] = notifiedCase(CaseStatus::AppointmentPlanning);
    $appointment = CaseAppointment::factory()->create([
        'team_id' => $team->id,
        'patient_case_id' => $case->id,
        'created_by_user_id' => $coordinator->id,
        'status' => AppointmentStatus::AwaitingConfirmation,
        'proposed_starts_at' => '2026-12-01 08:00:00',
        'location' => 'International Patient Clinic, floor 2',
    ]);

    $this->actingAs($coordinator)
        ->post(route('organizations.appointments.transition', ['current_team' => $team, 'case' => $case, 'appointment' => $appointment]), [
            'action' => 'confirm',
            'hospital_slot_verified' => true,
            'confirmation_source' => 'manual_hospital_verified',
            'confirmed_starts_at' => '2026-12-01 08:00:00',
        ])
        ->assertRedirect();

    $message = CaseCommunication::query()->where('patient_case_id', $case->id)->sole();

    expect($message->status)->toBe(CommunicationStatus::Sent)
        ->and($message->body)->toContain('Tuesday 1 December 2026, 11:00 (Europe/Istanbul)')
        ->and($message->body)->toContain('Where: International Patient Clinic, floor 2');
});

test('a doctor request for more information drafts a message saying what is needed', function () {
    [, $team, $case] = notifiedCase(CaseStatus::AwaitingDoctorReview);
    $doctor = User::factory()->create();
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);
    $case->update(['assigned_doctor_id' => $doctor->id]);

    app(SubmitDoctorReview::class)->handle($case, $doctor, DoctorReviewOutcome::NeedMoreInformation, 'Labs missing.', 'Recent bloodwork and an ECG.');

    $draft = CaseCommunication::query()->where('patient_case_id', $case->id)->sole();

    expect($draft->status)->toBe(CommunicationStatus::AwaitingApproval)
        ->and($draft->body)->toContain("What we need from you:\nRecent bloodwork and an ECG.");
    Mail::assertNothingSent();
});

test('confirming travel prepares an arrival message for approval, once', function () {
    [$coordinator, , $case] = notifiedCase(CaseStatus::TravelCoordination);
    $travel = [
        'status' => 'confirmed',
        'expected_arrival_date' => '2026-12-01',
        'airport_pickup_required' => true,
        'airport_pickup_details' => 'Driver at arrivals exit B',
        'accommodation' => 'Partner hotel near the hospital',
    ];

    app(UpsertTravelArrangement::class)->handle($case, $coordinator, $travel);
    app(UpsertTravelArrangement::class)->handle($case, $coordinator, $travel);

    $draft = CaseCommunication::query()->where('patient_case_id', $case->id)->sole();

    expect($draft->status)->toBe(CommunicationStatus::AwaitingApproval)
        ->and($draft->body)->toContain('Arrival: Tuesday 1 December 2026')
        ->and($draft->body)->toContain('Airport pickup: arranged (Driver at arrivals exit B)');
    Mail::assertNothingSent();
});

test('patients can accept a proposal from their case page', function () {
    [$coordinator, $team, $case] = notifiedCase(CaseStatus::ProposalSent);
    $proposal = approvedProposal($case, $coordinator);
    $proposal->update(['status' => TreatmentProposalStatus::SentToPatient, 'sent_at' => now()]);
    $token = $case->issuePortalAccessToken();
    $portal = ['organization' => $team->slug, 'reference' => $case->reference];

    $this->get(route('portal.cases.show', [...$portal, 'token' => $token]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('proposal.procedure', 'Cardiac evaluation package')
            ->where('proposal.can_respond', true));

    $this->post(route('portal.cases.proposals.respond', [...$portal, 'proposal' => $proposal->uuid]), [
        'token' => $token,
        'response' => 'accepted',
    ])->assertRedirect();

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::PatientAccepted)
        ->and(CaseTask::query()->where('patient_case_id', $case->id)->where('title', 'like', 'Patient accepted proposal%')->exists())->toBeTrue();

    $this->post(route('portal.cases.proposals.respond', [...$portal, 'proposal' => $proposal->uuid]), [
        'token' => $token,
        'response' => 'declined',
    ])->assertSessionHas('portal_notice', 'proposal_closed');

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::PatientAccepted);
});

test('a proposal cannot be answered without the case link', function () {
    [$coordinator, $team, $case] = notifiedCase(CaseStatus::ProposalSent);
    $proposal = approvedProposal($case, $coordinator);
    $proposal->update(['status' => TreatmentProposalStatus::SentToPatient]);
    $case->issuePortalAccessToken();

    $this->post(route('portal.cases.proposals.respond', ['organization' => $team->slug, 'reference' => $case->reference, 'proposal' => $proposal->uuid]), [
        'token' => 'not-the-token',
        'response' => 'accepted',
    ])->assertForbidden();

    expect($proposal->fresh()->status)->toBe(TreatmentProposalStatus::SentToPatient);
});
