<?php

use App\Actions\Patients\AssignPatientCase;
use App\Actions\Patients\CreatePatientFollowUp;
use App\Actions\Patients\TransitionPatientCaseStatus;
use App\Enums\CaseStatus;
use App\Enums\DocumentProcessingStatus;
use App\Enums\FollowUpType;
use App\Enums\TeamRole;
use App\Events\Patients\DocumentProcessingCompleted;
use App\Events\Patients\PatientEnquirySubmitted;
use App\Listeners\Patients\SendDocumentReadyNotifications;
use App\Listeners\Patients\SendPatientEnquiryNotifications;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\PatientEnquiry;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Patients\CaseAssignedNotification;
use App\Notifications\Patients\CaseStatusUpdatedNotification;
use App\Notifications\Patients\DocumentReadyNotification;
use App\Notifications\Patients\FollowUpScheduledNotification;
use App\Notifications\Patients\NewEnquiryStaffNotification;
use App\Notifications\Patients\PatientEnquiryReceivedNotification;
use Illuminate\Support\Facades\Notification;

function notificationCase(Team $team, array $overrides = []): PatientCase
{
    $patient = Patient::factory()->create([
        'team_id' => $team->id,
        'email' => 'patient@example.test',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    $enquiry = PatientEnquiry::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'reason_for_contact' => 'Second opinion for cardiology.',
    ]);

    return PatientCase::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $patient->id,
        'patient_enquiry_id' => $enquiry->id,
        'status' => CaseStatus::NewEnquiry,
        'reference' => 'CF-2026-880001',
        ...$overrides,
    ]);
}

test('intake submission emails the patient and coordinating staff', function () {
    Notification::fake();

    $owner = User::factory()->create(['name' => 'Owner User']);
    $coordinator = User::factory()->create(['name' => 'Coord User']);
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);

    $case = notificationCase($team);
    $portalToken = 'portal-token-example';

    (new SendPatientEnquiryNotifications)->handle(
        new PatientEnquirySubmitted(
            enquiryId: $case->patient_enquiry_id,
            caseId: $case->id,
            portalToken: $portalToken,
        ),
    );

    Notification::assertSentOnDemand(
        PatientEnquiryReceivedNotification::class,
        function (PatientEnquiryReceivedNotification $notification, array $channels, object $notifiable) use ($case, $portalToken) {
            expect($notification->case->is($case))->toBeTrue()
                ->and($notification->portalToken)->toBe($portalToken)
                ->and($notifiable->routes['mail'] ?? null)->toBe('patient@example.test');

            $mail = $notification->toMail($notifiable);

            expect($mail->subject)->toContain($case->reference);

            return true;
        },
    );

    Notification::assertSentTo($owner, NewEnquiryStaffNotification::class);
    Notification::assertSentTo($coordinator, NewEnquiryStaffNotification::class);
});

test('assigning a case notifies the new coordinator and doctor', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $coordinator = User::factory()->create(['name' => 'Case Coordinator']);
    $doctor = User::factory()->create(['name' => 'Case Doctor']);
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);

    $case = notificationCase($team);

    app(AssignPatientCase::class)->handle(
        case: $case,
        actor: $owner,
        coordinator: $coordinator,
        doctor: $doctor,
    );

    Notification::assertSentTo(
        $coordinator,
        CaseAssignedNotification::class,
        fn (CaseAssignedNotification $notification) => $notification->roleLabel === 'Coordinator',
    );

    Notification::assertSentTo(
        $doctor,
        CaseAssignedNotification::class,
        fn (CaseAssignedNotification $notification) => $notification->roleLabel === 'Doctor',
    );

    Notification::assertNotSentTo($owner, CaseAssignedNotification::class);
});

test('patient-visible status changes notify assigned staff and the patient', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $doctor = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);
    $team->members()->attach($doctor, ['role' => TeamRole::Doctor->value]);

    $case = notificationCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
        'assigned_doctor_id' => $doctor->id,
        'status' => CaseStatus::NewEnquiry,
    ]);

    app(TransitionPatientCaseStatus::class)->handle(
        case: $case,
        to: CaseStatus::AwaitingDocuments,
        actor: $owner,
        note: 'Waiting on MRI',
    );

    Notification::assertSentTo(
        $coordinator,
        CaseStatusUpdatedNotification::class,
        fn (CaseStatusUpdatedNotification $notification) => $notification->audience === 'staff'
            && $notification->to === CaseStatus::AwaitingDocuments,
    );

    Notification::assertSentTo(
        $doctor,
        CaseStatusUpdatedNotification::class,
        fn (CaseStatusUpdatedNotification $notification) => $notification->audience === 'staff',
    );

    Notification::assertSentOnDemand(
        CaseStatusUpdatedNotification::class,
        function (CaseStatusUpdatedNotification $notification, array $channels, object $notifiable) {
            return $notification->audience === 'patient'
                && ($notifiable->routes['mail'] ?? null) === 'patient@example.test';
        },
    );
});

test('document processing completion emails the assigned coordinator', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);

    $case = notificationCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
    ]);

    $document = PatientDocument::factory()->create([
        'team_id' => $team->id,
        'patient_id' => $case->patient_id,
        'patient_case_id' => $case->id,
        'original_filename' => 'mri-report.pdf',
        'processing_status' => DocumentProcessingStatus::Completed,
    ]);

    (new SendDocumentReadyNotifications)->handle(
        new DocumentProcessingCompleted(
            documentId: $document->id,
            caseId: $case->id,
            teamId: $team->id,
        ),
    );

    Notification::assertSentTo(
        $coordinator,
        DocumentReadyNotification::class,
        fn (DocumentReadyNotification $notification) => $notification->document->is($document),
    );

    Notification::assertNotSentTo($owner, DocumentReadyNotification::class);
});

test('scheduling a follow-up emails the assigned user', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $coordinator = User::factory()->create();
    $team = Team::factory()->create();
    $team->ensureOrganizationSetting();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($coordinator, ['role' => TeamRole::Coordinator->value]);

    $case = notificationCase($team, [
        'assigned_coordinator_id' => $coordinator->id,
        'status' => CaseStatus::FollowUp,
    ]);

    app(CreatePatientFollowUp::class)->handle($case, $owner, [
        'title' => 'Two-week outcome review',
        'description' => 'Confirm recovery progress with the patient.',
        'follow_up_type' => FollowUpType::OutcomeReview->value,
        'due_at' => now()->addDays(14)->toDateTimeString(),
        'assigned_user_id' => $coordinator->id,
    ]);

    Notification::assertSentTo(
        $coordinator,
        FollowUpScheduledNotification::class,
        function (FollowUpScheduledNotification $notification) use ($case) {
            $mail = $notification->toMail($case->assignedCoordinator);

            expect($mail->subject)->toContain($case->reference);

            return $notification->followUp->title === 'Two-week outcome review';
        },
    );
});
