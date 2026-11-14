<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organizations\AssistantController;
use App\Http\Controllers\Organizations\AuditLogController;
use App\Http\Controllers\Organizations\BulkCaseAssignmentController;
use App\Http\Controllers\Organizations\CaseAppointmentController;
use App\Http\Controllers\Organizations\CaseCommunicationController;
use App\Http\Controllers\Organizations\CaseDocumentUploadController;
use App\Http\Controllers\Organizations\CaseTaskController;
use App\Http\Controllers\Organizations\ClinicalApprovalController;
use App\Http\Controllers\Organizations\ClinicalObservationController;
use App\Http\Controllers\Organizations\ClinicalRecommendationController;
use App\Http\Controllers\Organizations\ClinicalReviewController;
use App\Http\Controllers\Organizations\CoordinationController;
use App\Http\Controllers\Organizations\DataToolsController;
use App\Http\Controllers\Organizations\DepartmentController;
use App\Http\Controllers\Organizations\DoctorQueueController;
use App\Http\Controllers\Organizations\MessageTemplateController;
use App\Http\Controllers\Organizations\OrganizationAiController;
use App\Http\Controllers\Organizations\OrganizationLogoController;
use App\Http\Controllers\Organizations\OrganizationPreferencesController;
use App\Http\Controllers\Organizations\OrganizationRolesController;
use App\Http\Controllers\Organizations\OrganizationSettingsController;
use App\Http\Controllers\Organizations\PatientCaseAssignmentController;
use App\Http\Controllers\Organizations\PatientCaseController;
use App\Http\Controllers\Organizations\PatientCaseExportController;
use App\Http\Controllers\Organizations\PatientCaseStatusController;
use App\Http\Controllers\Organizations\PatientDirectoryController;
use App\Http\Controllers\Organizations\PatientDocumentController;
use App\Http\Controllers\Organizations\PatientDocumentReviewController;
use App\Http\Controllers\Organizations\PatientFollowUpController;
use App\Http\Controllers\Organizations\PatientProfileController;
use App\Http\Controllers\Organizations\SavedCaseViewController;
use App\Http\Controllers\Organizations\StaffCaseIntakeController;
use App\Http\Controllers\Organizations\StorageSettingsController;
use App\Http\Controllers\Organizations\TravelArrangementController;
use App\Http\Controllers\Organizations\TreatmentProposalController;
use App\Http\Controllers\Organizations\WorkflowExecutionController;
use App\Http\Controllers\Organizations\WorkQueueController;
use App\Http\Controllers\Organizations\WorkQueueItemController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformOrganizationController;
use App\Http\Controllers\Portal\PatientCaseTrackingController;
use App\Http\Controllers\Portal\PatientIntakeController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome', [
    'contactEmail' => config('careflow.contact_email'),
]))->name('home');

Route::prefix('p/{organization}')
    ->scopeBindings()
    ->group(function () {
        Route::get('logo', [OrganizationLogoController::class, 'show'])
            ->name('portal.logo');
        Route::get('intake', [PatientIntakeController::class, 'create'])
            ->name('portal.intake.create');
        Route::post('intake', [PatientIntakeController::class, 'store'])
            ->middleware('throttle:patient-intake')
            ->name('portal.intake.store');
        Route::get('intake/confirmation/{reference}', [PatientIntakeController::class, 'confirmation'])
            ->name('portal.intake.confirmation');
        Route::post('cases/{reference}/documents', [PatientCaseTrackingController::class, 'uploadDocuments'])
            ->middleware('throttle:20,1')
            ->name('portal.cases.documents.store');
        Route::post('cases/{reference}/messages', [PatientCaseTrackingController::class, 'reply'])
            ->middleware('throttle:20,1')
            ->name('portal.cases.messages.store');
        Route::post('cases/{reference}/proposals/{proposal}/respond', [PatientCaseTrackingController::class, 'respondToProposal'])
            ->middleware('throttle:10,1')
            ->name('portal.cases.proposals.respond');
        Route::get('cases/{reference}', [PatientCaseTrackingController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('portal.cases.show');
    });

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTwoFactorEnabled::class, EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('doctor-queue', DoctorQueueController::class)->name('organizations.doctor-queue');
        Route::get('work', WorkQueueController::class)->name('organizations.work');
        Route::get('assistant', [AssistantController::class, 'show'])->name('organizations.assistant');
        Route::get('assistant/state', [AssistantController::class, 'state'])->name('organizations.assistant.state');
        Route::post('assistant/messages', [AssistantController::class, 'message'])->middleware('throttle:30,1')->name('organizations.assistant.messages');
        Route::post('assistant/decisions', [AssistantController::class, 'decide'])->middleware('throttle:30,1')->name('organizations.assistant.decisions');
        Route::delete('assistant/conversation', [AssistantController::class, 'clear'])->name('organizations.assistant.clear');
        Route::patch('assistant/settings', [AssistantController::class, 'updateSettings'])->name('organizations.assistant.settings');
        Route::get('patients', PatientDirectoryController::class)->name('organizations.patients.index');
        Route::get('patients/{patient:uuid}', [PatientProfileController::class, 'show'])->name('organizations.patients.show');
        Route::patch('patients/{patient:uuid}', [PatientProfileController::class, 'update'])->name('organizations.patients.update');
        Route::get('coordination', CoordinationController::class)->name('organizations.coordination');
        Route::get('organization/audit', AuditLogController::class)->name('organizations.audit');
        Route::get('organization/roles', OrganizationRolesController::class)->name('organizations.roles');
        Route::get('organization/preferences', [OrganizationPreferencesController::class, 'edit'])->name('organizations.preferences.edit');
        Route::patch('organization/preferences', [OrganizationPreferencesController::class, 'update'])->name('organizations.preferences.update');
        Route::patch('organization/preferences/workflows/{workflow}', [OrganizationPreferencesController::class, 'toggleWorkflow'])->name('organizations.preferences.workflows.toggle');
        Route::get('workflows', [WorkflowExecutionController::class, 'index'])
            ->name('organizations.workflows.index');

        Route::get('organization/settings', [OrganizationSettingsController::class, 'edit'])
            ->name('organizations.settings.edit');
        Route::patch('organization/settings', [OrganizationSettingsController::class, 'update'])
            ->name('organizations.settings.update');
        Route::patch('organization/ai', [OrganizationAiController::class, 'update'])
            ->name('organizations.ai.update');

        Route::get('organization/message-templates', [MessageTemplateController::class, 'index'])
            ->name('organizations.message-templates.index');
        Route::put('organization/message-templates', [MessageTemplateController::class, 'upsert'])
            ->name('organizations.message-templates.upsert');

        Route::patch('organization/departments/{department}/reorder', [DepartmentController::class, 'reorder'])
            ->name('organizations.departments.reorder');

        Route::resource('organization/departments', DepartmentController::class)
            ->except(['show'])
            ->names([
                'index' => 'organizations.departments.index',
                'create' => 'organizations.departments.create',
                'store' => 'organizations.departments.store',
                'edit' => 'organizations.departments.edit',
                'update' => 'organizations.departments.update',
                'destroy' => 'organizations.departments.destroy',
            ])
            ->parameters([
                'departments' => 'department',
            ]);

        Route::get('organization/data', [DataToolsController::class, 'show'])->name('organizations.data-tools');
        Route::patch('organization/data/retention', [DataToolsController::class, 'updateRetention'])->name('organizations.data-tools.retention');
        Route::post('organization/data/purge-expired', [DataToolsController::class, 'purgeExpired'])->name('organizations.data-tools.purge');
        Route::get('organization/data/patients/{patient:uuid}/export', [DataToolsController::class, 'exportPatient'])->name('organizations.data-tools.export');
        Route::post('organization/data/patients/{patient:uuid}/erase', [DataToolsController::class, 'erasePatient'])->name('organizations.data-tools.erase');
        Route::get('organization/storage', [StorageSettingsController::class, 'edit'])->name('organizations.storage.edit');
        Route::put('organization/storage', [StorageSettingsController::class, 'update'])->name('organizations.storage.update');
        Route::post('organization/storage/move', [StorageSettingsController::class, 'move'])->name('organizations.storage.move');
        Route::post('organization/logo', [OrganizationLogoController::class, 'store'])->name('organizations.logo.store');
        Route::delete('organization/logo', [OrganizationLogoController::class, 'destroy'])->name('organizations.logo.destroy');

        Route::get('cases/export', PatientCaseExportController::class)->name('organizations.cases.export');
        Route::post('cases/bulk-assignment', BulkCaseAssignmentController::class)->name('organizations.cases.bulk-assignment');
        Route::post('case-views', [SavedCaseViewController::class, 'store'])->name('organizations.case-views.store');
        Route::delete('case-views/{view}', [SavedCaseViewController::class, 'destroy'])->name('organizations.case-views.destroy');
        Route::get('cases', [PatientCaseController::class, 'index'])
            ->name('organizations.cases.index');
        Route::post('cases', StaffCaseIntakeController::class)
            ->middleware('throttle:30,1')
            ->name('organizations.cases.store');
        Route::get('cases/{case}', [PatientCaseController::class, 'show'])
            ->name('organizations.cases.show');
        Route::patch('cases/{case}/status', [PatientCaseStatusController::class, 'update'])
            ->name('organizations.cases.status.update');
        Route::patch('cases/{case}/assignment', [PatientCaseAssignmentController::class, 'update'])
            ->name('organizations.cases.assignment.update');

        Route::get('cases/{case}/documents/{document}', [PatientDocumentController::class, 'show'])
            ->name('organizations.documents.show');
        Route::get('cases/{case}/documents/{document}/preview', [PatientDocumentController::class, 'preview'])
            ->name('organizations.documents.preview');
        Route::get('cases/{case}/documents/{document}/download', [PatientDocumentController::class, 'download'])
            ->name('organizations.documents.download');
        Route::post('cases/{case}/documents', CaseDocumentUploadController::class)
            ->middleware('throttle:30,1')
            ->name('organizations.documents.store');
        Route::post('cases/{case}/documents/{document}/reprocess', [PatientDocumentController::class, 'reprocess'])
            ->name('organizations.documents.reprocess');
        Route::post('cases/{case}/documents/{document}/review/confirm', [PatientDocumentReviewController::class, 'confirm'])
            ->name('organizations.documents.review.confirm');
        Route::post('cases/{case}/documents/{document}/translation/sign-off', [PatientDocumentReviewController::class, 'signOffTranslation'])
            ->name('organizations.documents.translation.sign-off');
        Route::patch('cases/{case}/documents/{document}/review', [PatientDocumentReviewController::class, 'update'])
            ->name('organizations.documents.review.update');

        Route::post('cases/{case}/clinical/observations', [ClinicalObservationController::class, 'store'])
            ->name('organizations.clinical.observations.store');
        Route::post('cases/{case}/clinical/recommendations', [ClinicalRecommendationController::class, 'store'])
            ->name('organizations.clinical.recommendations.store');
        Route::patch('cases/{case}/clinical/recommendations/{recommendation}', [ClinicalRecommendationController::class, 'update'])
            ->name('organizations.clinical.recommendations.update');
        Route::post('cases/{case}/clinical/reviews', [ClinicalReviewController::class, 'store'])
            ->name('organizations.clinical.reviews.store');
        Route::post('cases/{case}/clinical/recommendations/{recommendation}/decision', [ClinicalApprovalController::class, 'store'])
            ->name('organizations.clinical.approvals.store');

        Route::middleware(EnsureModuleEnabled::class.':proposals')->group(function () {
            Route::post('cases/{case}/proposals', [TreatmentProposalController::class, 'store'])
                ->name('organizations.proposals.store');
            Route::patch('cases/{case}/proposals/{proposal}', [TreatmentProposalController::class, 'update'])
                ->name('organizations.proposals.update');
            Route::post('cases/{case}/proposals/{proposal}/transition', [TreatmentProposalController::class, 'transition'])
                ->name('organizations.proposals.transition');
        });

        Route::middleware(EnsureModuleEnabled::class.':appointments')->group(function () {
            Route::post('cases/{case}/appointments', [CaseAppointmentController::class, 'store'])
                ->name('organizations.appointments.store');
            Route::patch('cases/{case}/appointments/{appointment}', [CaseAppointmentController::class, 'update'])
                ->name('organizations.appointments.update');
            Route::post('cases/{case}/appointments/{appointment}/transition', [CaseAppointmentController::class, 'transition'])
                ->name('organizations.appointments.transition');
        });

        Route::middleware(EnsureModuleEnabled::class.':travel')->group(function () {
            Route::put('cases/{case}/travel', [TravelArrangementController::class, 'upsert'])
                ->name('organizations.travel.upsert');
        });

        Route::post('cases/{case}/tasks', [CaseTaskController::class, 'store'])
            ->name('organizations.tasks.store');
        Route::patch('cases/{case}/tasks/{task}', [CaseTaskController::class, 'update'])
            ->name('organizations.tasks.update');
        Route::post('cases/{case}/tasks/{task}/transition', [CaseTaskController::class, 'transition'])
            ->name('organizations.tasks.transition');

        Route::middleware(EnsureModuleEnabled::class.':communications')->group(function () {
            Route::post('cases/{case}/communications', [CaseCommunicationController::class, 'store'])
                ->name('organizations.communications.store');
            Route::post('cases/{case}/communications/{communication}/approve', [CaseCommunicationController::class, 'approve'])
                ->name('organizations.communications.approve');
            Route::patch('cases/{case}/communications/{communication}', [CaseCommunicationController::class, 'update'])
                ->name('organizations.communications.update');
            Route::delete('cases/{case}/communications/{communication}', [CaseCommunicationController::class, 'destroy'])
                ->name('organizations.communications.destroy');
        });

        Route::post('cases/{case}/follow-ups', [PatientFollowUpController::class, 'store'])
            ->name('organizations.follow-ups.store');
        Route::patch('cases/{case}/follow-ups/{followUp}', [PatientFollowUpController::class, 'update'])
            ->name('organizations.follow-ups.update');
        Route::post('cases/{case}/follow-ups/{followUp}/complete', [PatientFollowUpController::class, 'complete'])
            ->name('organizations.follow-ups.complete');

        Route::post('work/cases/{case}/tasks/{task}/complete', [WorkQueueItemController::class, 'completeTask'])
            ->name('organizations.work.tasks.complete');
        Route::post('work/cases/{case}/follow-ups/{followUp}/complete', [WorkQueueItemController::class, 'completeFollowUp'])
            ->name('organizations.work.follow-ups.complete');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('platform/impersonation', [ImpersonationController::class, 'destroy'])
        ->name('platform.impersonation.stop');
});

Route::middleware(['auth', 'verified', EnsureTwoFactorEnabled::class, EnsurePlatformAdmin::class])
    ->prefix('platform')
    ->group(function () {
        Route::get('/', PlatformDashboardController::class)->name('platform.dashboard');
        Route::get('organizations/{organization}', [PlatformOrganizationController::class, 'edit'])->name('platform.organizations.edit');
        Route::patch('organizations/{organization}', [PlatformOrganizationController::class, 'update'])->name('platform.organizations.update');
        Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])
            ->name('platform.impersonation.start');
    });

require __DIR__.'/settings.php';
