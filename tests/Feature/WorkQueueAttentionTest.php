<?php

use App\Enums\CaseTaskStatus;
use App\Enums\ClinicalRecommendationStatus;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Enums\CommunicationStatus;
use App\Enums\FollowUpStatus;
use App\Enums\TeamRole;
use App\Enums\TreatmentProposalStatus;
use App\Models\CaseCommunication;
use App\Models\CaseTask;
use App\Models\ClinicalRecommendation;
use App\Models\DocumentExtraction;
use App\Models\DocumentProcessingRun;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\PatientFollowUp;
use App\Models\Team;
use App\Models\TreatmentProposal;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: Team, 1: User}
 */
function attentionTeam(TeamRole $role): array
{
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$team, $user];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function attentionCase(Team $team, array $attributes = []): PatientCase
{
    $patient = Patient::factory()->create(['team_id' => $team->id]);
    $enquiry = PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id]);

    return PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        ...$attributes,
    ]);
}

function attentionMessage(PatientCase $case, CommunicationDirection $direction, int $minutesAgo, CommunicationStatus $status = CommunicationStatus::Logged, string $body = 'Hello'): CaseCommunication
{
    return CaseCommunication::factory()->create([
        'team_id' => $case->team_id,
        'patient_case_id' => $case->id,
        'direction' => $direction,
        'channel' => $direction === CommunicationDirection::Internal ? CommunicationChannel::Internal : CommunicationChannel::PatientPortal,
        'status' => $status,
        'body' => $body,
        'created_at' => now()->subMinutes($minutesAgo),
    ]);
}

function attentionDocument(PatientCase $case, string $reviewStatus): PatientDocument
{
    $document = PatientDocument::factory()->create([
        'team_id' => $case->team_id,
        'patient_id' => $case->patient_id,
        'patient_case_id' => $case->id,
    ]);

    DocumentExtraction::factory()->create([
        'team_id' => $case->team_id,
        'patient_document_id' => $document->id,
        'document_processing_run_id' => DocumentProcessingRun::factory()->create([
            'team_id' => $case->team_id,
            'patient_document_id' => $document->id,
        ])->id,
        'review_status' => $reviewStatus,
    ]);

    return $document;
}

test('my work lists patients whose last message has not been answered', function () {
    [$team, $coordinator] = attentionTeam(TeamRole::Coordinator);
    $otherCoordinator = User::factory()->create();

    $waiting = attentionCase($team, ['assigned_coordinator_id' => $coordinator->id]);
    attentionMessage($waiting, CommunicationDirection::Outbound, 60, CommunicationStatus::Sent);
    attentionMessage($waiting, CommunicationDirection::Inbound, 30, body: 'When is my appointment?');
    attentionMessage($waiting, CommunicationDirection::Internal, 10);
    attentionMessage($waiting, CommunicationDirection::Outbound, 5, CommunicationStatus::Draft);

    $answered = attentionCase($team, ['assigned_coordinator_id' => $coordinator->id]);
    attentionMessage($answered, CommunicationDirection::Inbound, 30);
    attentionMessage($answered, CommunicationDirection::Outbound, 10, CommunicationStatus::Sent);

    $unassigned = attentionCase($team, ['assigned_coordinator_id' => null]);
    attentionMessage($unassigned, CommunicationDirection::Inbound, 20);

    $someoneElses = attentionCase($team, ['assigned_coordinator_id' => $otherCoordinator->id]);
    attentionMessage($someoneElses, CommunicationDirection::Inbound, 20);

    $this->actingAs($coordinator)
        ->get(route('organizations.work', ['current_team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSeeReplies', true)
            ->has('sections.replies', 2)
            ->where('sections.replies', fn ($replies) => collect($replies)->pluck('case.reference')->sort()->values()->all()
                === collect([$waiting->reference, $unassigned->reference])->sort()->values()->all())
            ->where('sections.replies', fn ($replies) => collect($replies)->firstWhere('case.reference', $waiting->reference)['title'] === 'When is my appointment?'
                && collect($replies)->firstWhere('case.reference', $waiting->reference)['tab'] === 'communications'));
});

test('my work lists documents with unchecked AI results and links to the document', function () {
    [$team, $coordinator] = attentionTeam(TeamRole::Coordinator);
    $mine = attentionCase($team, ['assigned_coordinator_id' => $coordinator->id]);
    $someoneElses = attentionCase($team, ['assigned_coordinator_id' => User::factory()->create()->id]);

    $toReview = attentionDocument($mine, 'unreviewed');
    attentionDocument($mine, 'confirmed');
    attentionDocument($someoneElses, 'unreviewed');

    $this->actingAs($coordinator)
        ->get(route('organizations.work', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canReviewDocuments', true)
            ->has('sections.documents', 1)
            ->where('sections.documents.0.id', $toReview->uuid)
            ->where('sections.documents.0.url', route('organizations.documents.show', [
                'current_team' => $team->slug,
                'case' => $mine->reference,
                'document' => $toReview->uuid,
            ])));
});

test('doctors see proposals and recommendations waiting for their approval', function () {
    [$team, $doctor] = attentionTeam(TeamRole::Doctor);
    $mine = attentionCase($team, ['assigned_doctor_id' => $doctor->id]);
    $notMine = attentionCase($team, ['assigned_doctor_id' => null]);

    TreatmentProposal::factory()->create(['team_id' => $team->id, 'patient_case_id' => $mine->id, 'status' => TreatmentProposalStatus::AwaitingClinicalApproval]);
    TreatmentProposal::factory()->create(['team_id' => $team->id, 'patient_case_id' => attentionCase($team, ['assigned_doctor_id' => $doctor->id])->id, 'status' => TreatmentProposalStatus::Draft]);
    TreatmentProposal::factory()->create(['team_id' => $team->id, 'patient_case_id' => $notMine->id, 'status' => TreatmentProposalStatus::AwaitingClinicalApproval]);
    ClinicalRecommendation::factory()->create(['team_id' => $team->id, 'patient_case_id' => $mine->id, 'status' => ClinicalRecommendationStatus::AwaitingApproval]);
    ClinicalRecommendation::factory()->create(['team_id' => $team->id, 'patient_case_id' => attentionCase($team, ['assigned_doctor_id' => $doctor->id])->id, 'status' => ClinicalRecommendationStatus::AwaitingApproval, 'is_ai_generated' => true]);

    $this->actingAs($doctor)
        ->get(route('organizations.work', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canApproveClinical', true)
            ->where('canSeeReplies', true)
            ->has('sections.approvals', 2)
            ->where('sections.approvals.0.tab', 'proposal')
            ->where('sections.approvals.1.tab', 'evaluation')
            ->where('attentionCount', 2)
            ->where('pendingWorkCount', 2));
});

test('coordinators do not get an approvals section', function () {
    [$team, $coordinator] = attentionTeam(TeamRole::Coordinator);
    $case = attentionCase($team, ['assigned_coordinator_id' => $coordinator->id]);
    TreatmentProposal::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'status' => TreatmentProposalStatus::AwaitingClinicalApproval]);

    $this->actingAs($coordinator)
        ->get(route('organizations.work', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canApproveClinical', false)
            ->has('sections.approvals', 0));
});

test('the header bell counts everything on my work', function () {
    [$team, $coordinator] = attentionTeam(TeamRole::Coordinator);
    $case = attentionCase($team, ['assigned_coordinator_id' => $coordinator->id]);

    CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $coordinator->id]);
    PatientFollowUp::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $coordinator->id]);
    attentionMessage($case, CommunicationDirection::Inbound, 10);
    attentionDocument($case, 'unreviewed');

    $this->actingAs($coordinator)
        ->get(route('organizations.cases.index', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->where('pendingWorkCount', 4));

    $this->actingAs($coordinator)
        ->get(route('organizations.work', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attentionCount', 4)
            ->where('pendingWorkCount', 4));
});

test('a task can be ticked off from my work and the user stays on my work', function () {
    [$team, $coordinator] = attentionTeam(TeamRole::Coordinator);
    $case = attentionCase($team, ['assigned_coordinator_id' => $coordinator->id]);
    $task = CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $coordinator->id]);

    $this->actingAs($coordinator)
        ->get(route('organizations.work', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page->where('sections.tasks.0.complete_url', route('organizations.work.tasks.complete', [
            'current_team' => $team->slug,
            'case' => $case->reference,
            'task' => $task->uuid,
        ])));

    $this->actingAs($coordinator)
        ->post(route('organizations.work.tasks.complete', ['current_team' => $team, 'case' => $case, 'task' => $task]))
        ->assertStatus(302)
        ->assertRedirect(route('organizations.work', ['current_team' => $team->slug]));

    expect($task->fresh()->status)->toBe(CaseTaskStatus::Completed)
        ->and($task->fresh()->completed_by_user_id)->toBe($coordinator->id);
});

test('a follow-up can be marked done from my work', function () {
    [$team, $coordinator] = attentionTeam(TeamRole::Coordinator);
    $case = attentionCase($team, ['assigned_coordinator_id' => $coordinator->id]);
    $followUp = PatientFollowUp::factory()->create(['team_id' => $team->id, 'patient_case_id' => $case->id, 'assigned_user_id' => $coordinator->id]);

    $this->actingAs($coordinator)
        ->post(route('organizations.work.follow-ups.complete', ['current_team' => $team, 'case' => $case, 'followUp' => $followUp]))
        ->assertStatus(302)
        ->assertRedirect(route('organizations.work', ['current_team' => $team->slug]));

    expect($followUp->fresh()->status)->toBe(FollowUpStatus::Completed);
});

test('staff cannot tick off tasks on cases they cannot open', function () {
    [$team, $doctor] = attentionTeam(TeamRole::Doctor);
    $hidden = attentionCase($team, ['assigned_doctor_id' => null]);
    $task = CaseTask::factory()->create(['team_id' => $team->id, 'patient_case_id' => $hidden->id, 'assigned_user_id' => $doctor->id]);

    $this->actingAs($doctor)
        ->post(route('organizations.work.tasks.complete', ['current_team' => $team, 'case' => $hidden, 'task' => $task]))
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(CaseTaskStatus::Open);
});
