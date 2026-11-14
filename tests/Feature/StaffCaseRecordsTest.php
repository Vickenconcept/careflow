<?php

use App\Enums\CaseStatus;
use App\Enums\ConsentType;
use App\Enums\TeamRole;
use App\Events\Patients\PatientEnquirySubmitted;
use App\Jobs\Patients\ProcessPatientDocument;
use App\Models\CaseActivity;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientConsent;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    Notification::fake();
});

/**
 * @return array{0: Team, 1: User}
 */
function staffRecordsTeam(TeamRole $role = TeamRole::Coordinator): array
{
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return [$team, $user];
}

function staffOwnedCase(Team $team, User $coordinator, CaseStatus $status = CaseStatus::AwaitingDocuments): PatientCase
{
    $patient = Patient::factory()->create(['team_id' => $team->id, 'email' => 'ayla@example.test']);

    return PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => PatientEnquiry::factory()->create(['team_id' => $team->id, 'patient_id' => $patient->id])->id,
        'assigned_coordinator_id' => $coordinator->id,
        'status' => $status,
    ]);
}

test('coordinators can add documents the patient sent by email', function () {
    [$team, $coordinator] = staffRecordsTeam();
    $team->organizationSetting->update(['ai_processing_enabled' => true]);
    $case = staffOwnedCase($team, $coordinator);
    PatientConsent::factory()->create(['team_id' => $team->id, 'patient_id' => $case->patient_id, 'consent_type' => ConsentType::AiProcessing]);

    $this->actingAs($coordinator)
        ->post(route('organizations.documents.store', ['current_team' => $team, 'case' => $case]), [
            'documents' => [UploadedFile::fake()->create('mri.pdf', 40, 'application/pdf')],
            'category' => 'imaging_report',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = PatientDocument::query()->where('patient_case_id', $case->id)->sole();

    expect($document->category->value)->toBe('imaging_report')
        ->and($case->fresh()->status)->toBe(CaseStatus::DocumentsReceived)
        ->and(CaseActivity::query()->where('patient_case_id', $case->id)->where('actor_user_id', $coordinator->id)->exists())->toBeTrue();

    Queue::assertPushed(ProcessPatientDocument::class);
});

test('staff without case management rights cannot add documents', function () {
    [$team, $coordinator] = staffRecordsTeam();
    $case = staffOwnedCase($team, $coordinator);
    $viewer = User::factory()->create();
    $team->members()->attach($viewer, ['role' => TeamRole::ManagementViewer->value]);

    $this->actingAs($viewer)
        ->post(route('organizations.documents.store', ['current_team' => $team, 'case' => $case]), [
            'documents' => [UploadedFile::fake()->create('mri.pdf', 40, 'application/pdf')],
        ])
        ->assertForbidden();

    expect(PatientDocument::query()->count())->toBe(0);
});

test('coordinators can create a case for a phone enquiry', function () {
    Event::fake([PatientEnquirySubmitted::class]);
    [$team, $coordinator] = staffRecordsTeam();

    $this->actingAs($coordinator)
        ->post(route('organizations.cases.store', ['current_team' => $team]), [
            'first_name' => 'Hans',
            'last_name' => 'Meier',
            'email' => 'Hans.Meier@Example.test',
            'preferred_language' => 'en',
            'source' => 'phone',
            'reason_for_contact' => 'Second opinion on a knee replacement',
            'consent_confirmed' => true,
            'ai_processing_consent' => true,
            'email_patient' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $case = PatientCase::query()->where('team_id', $team->id)->sole();
    $patient = $case->patient;

    expect($patient->email)->toBe('hans.meier@example.test')
        ->and($case->status)->toBe(CaseStatus::NewEnquiry)
        ->and($case->enquiry->additional_comments)->toContain('Phone call')
        ->and(PatientConsent::query()->where('patient_id', $patient->id)->pluck('consent_type')->map->value->sort()->values()->all())
        ->toBe([ConsentType::AiProcessing->value, ConsentType::Enquiry->value, ConsentType::HealthData->value])
        ->and(CaseActivity::query()->where('patient_case_id', $case->id)->where('actor_user_id', $coordinator->id)->exists())->toBeTrue();

    Event::assertDispatched(PatientEnquirySubmitted::class, fn (PatientEnquirySubmitted $event) => $event->caseId === $case->id && $event->portalToken !== '');
});

test('a staff-created case can skip emailing the patient and reuses an existing patient', function () {
    Event::fake([PatientEnquirySubmitted::class]);
    [$team, $coordinator] = staffRecordsTeam();
    $existing = staffOwnedCase($team, $coordinator)->patient;

    $this->actingAs($coordinator)
        ->post(route('organizations.cases.store', ['current_team' => $team]), [
            'first_name' => 'Ayla',
            'last_name' => 'Demir',
            'email' => 'ayla@example.test',
            'preferred_language' => 'tr',
            'source' => 'email',
            'reason_for_contact' => 'Follow-up treatment',
            'consent_confirmed' => true,
            'email_patient' => false,
        ])
        ->assertRedirect();

    expect(Patient::query()->where('team_id', $team->id)->count())->toBe(1)
        ->and($existing->cases()->count())->toBe(2);
    Event::assertDispatched(PatientEnquirySubmitted::class, fn (PatientEnquirySubmitted $event) => $event->portalToken === '');
});

test('a case cannot be created without confirming the patient agreed', function () {
    [$team, $coordinator] = staffRecordsTeam();

    $this->actingAs($coordinator)
        ->post(route('organizations.cases.store', ['current_team' => $team]), [
            'first_name' => 'Hans',
            'last_name' => 'Meier',
            'email' => 'hans@example.test',
            'preferred_language' => 'en',
            'source' => 'phone',
            'reason_for_contact' => 'Knee',
        ])
        ->assertSessionHasErrors('consent_confirmed');

    expect(PatientCase::query()->count())->toBe(0);
});

test('staff can view a patient profile and correct their email', function () {
    [$team, $coordinator] = staffRecordsTeam();
    $case = staffOwnedCase($team, $coordinator);
    $patient = $case->patient;

    $this->actingAs($coordinator)
        ->get(route('organizations.patients.show', ['current_team' => $team, 'patient' => $patient->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/patients/show')
            ->where('patient.email', 'ayla@example.test')
            ->has('cases', 1)
            ->where('canEdit', true));

    $this->actingAs($coordinator)
        ->patch(route('organizations.patients.update', ['current_team' => $team, 'patient' => $patient->uuid]), [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'email' => 'ayla.demir@example.test',
            'preferred_language' => 'tr',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($patient->fresh()->email)->toBe('ayla.demir@example.test')
        ->and($patient->fresh()->preferred_language)->toBe('tr');
});

test('a patient email cannot clash with another patient at the same hospital', function () {
    [$team, $coordinator] = staffRecordsTeam();
    $patient = staffOwnedCase($team, $coordinator)->patient;
    Patient::factory()->create(['team_id' => $team->id, 'email' => 'taken@example.test']);

    $this->actingAs($coordinator)
        ->patch(route('organizations.patients.update', ['current_team' => $team, 'patient' => $patient->uuid]), [
            'first_name' => 'Ayla',
            'last_name' => 'Demir',
            'email' => 'taken@example.test',
            'preferred_language' => 'en',
        ])
        ->assertSessionHasErrors('email');
});

test('patients from another hospital are not visible', function () {
    [$team, $coordinator] = staffRecordsTeam();
    [$otherTeam, $otherCoordinator] = staffRecordsTeam();
    $otherPatient = staffOwnedCase($otherTeam, $otherCoordinator)->patient;

    $this->actingAs($coordinator)
        ->get(route('organizations.patients.show', ['current_team' => $team, 'patient' => $otherPatient->uuid]))
        ->assertNotFound();
});
