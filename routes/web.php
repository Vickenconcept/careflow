<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organizations\CaseAppointmentController;
use App\Http\Controllers\Organizations\CaseCommunicationController;
use App\Http\Controllers\Organizations\CaseTaskController;
use App\Http\Controllers\Organizations\ClinicalApprovalController;
use App\Http\Controllers\Organizations\ClinicalObservationController;
use App\Http\Controllers\Organizations\ClinicalRecommendationController;
use App\Http\Controllers\Organizations\ClinicalReviewController;
use App\Http\Controllers\Organizations\DepartmentController;
use App\Http\Controllers\Organizations\DoctorQueueController;
use App\Http\Controllers\Organizations\MessageTemplateController;
use App\Http\Controllers\Organizations\OrganizationSettingsController;
use App\Http\Controllers\Organizations\PatientCaseAssignmentController;
use App\Http\Controllers\Organizations\PatientCaseController;
use App\Http\Controllers\Organizations\PatientCaseStatusController;
use App\Http\Controllers\Organizations\PatientDocumentController;
use App\Http\Controllers\Organizations\PatientDocumentReviewController;
use App\Http\Controllers\Organizations\PatientFollowUpController;
use App\Http\Controllers\Organizations\TravelArrangementController;
use App\Http\Controllers\Organizations\TreatmentProposalController;
use App\Http\Controllers\Organizations\WorkflowExecutionController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Portal\PatientCaseTrackingController;
use App\Http\Controllers\Portal\PatientIntakeController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('p/{organization}')
    ->scopeBindings()
    ->group(function () {
        Route::get('intake', [PatientIntakeController::class, 'create'])
            ->name('portal.intake.create');
        Route::post('intake', [PatientIntakeController::class, 'store'])
            ->middleware('throttle:patient-intake')
            ->name('portal.intake.store');
        Route::get('intake/confirmation/{reference}', [PatientIntakeController::class, 'confirmation'])
            ->name('portal.intake.confirmation');
        Route::get('cases/{reference}', [PatientCaseTrackingController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('portal.cases.show');
    });

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('doctor-queue', DoctorQueueController::class)->name('organizations.doctor-queue');
        Route::get('workflows', [WorkflowExecutionController::class, 'index'])
            ->name('organizations.workflows.index');

        Route::get('organization/settings', [OrganizationSettingsController::class, 'edit'])
            ->name('organizations.settings.edit');
        Route::patch('organization/settings', [OrganizationSettingsController::class, 'update'])
            ->name('organizations.settings.update');

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

        Route::get('cases', [PatientCaseController::class, 'index'])
            ->name('organizations.cases.index');
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
        Route::post('cases/{case}/documents/{document}/reprocess', [PatientDocumentController::class, 'reprocess'])
            ->name('organizations.documents.reprocess');
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

        Route::post('cases/{case}/proposals', [TreatmentProposalController::class, 'store'])
            ->name('organizations.proposals.store');
        Route::patch('cases/{case}/proposals/{proposal}', [TreatmentProposalController::class, 'update'])
            ->name('organizations.proposals.update');
        Route::post('cases/{case}/proposals/{proposal}/transition', [TreatmentProposalController::class, 'transition'])
            ->name('organizations.proposals.transition');

        Route::post('cases/{case}/appointments', [CaseAppointmentController::class, 'store'])
            ->name('organizations.appointments.store');
        Route::patch('cases/{case}/appointments/{appointment}', [CaseAppointmentController::class, 'update'])
            ->name('organizations.appointments.update');
        Route::post('cases/{case}/appointments/{appointment}/transition', [CaseAppointmentController::class, 'transition'])
            ->name('organizations.appointments.transition');

        Route::put('cases/{case}/travel', [TravelArrangementController::class, 'upsert'])
            ->name('organizations.travel.upsert');

        Route::post('cases/{case}/tasks', [CaseTaskController::class, 'store'])
            ->name('organizations.tasks.store');
        Route::patch('cases/{case}/tasks/{task}', [CaseTaskController::class, 'update'])
            ->name('organizations.tasks.update');
        Route::post('cases/{case}/tasks/{task}/transition', [CaseTaskController::class, 'transition'])
            ->name('organizations.tasks.transition');

        Route::post('cases/{case}/communications', [CaseCommunicationController::class, 'store'])
            ->name('organizations.communications.store');
        Route::post('cases/{case}/communications/{communication}/approve', [CaseCommunicationController::class, 'approve'])
            ->name('organizations.communications.approve');

        Route::post('cases/{case}/follow-ups', [PatientFollowUpController::class, 'store'])
            ->name('organizations.follow-ups.store');
        Route::patch('cases/{case}/follow-ups/{followUp}', [PatientFollowUpController::class, 'update'])
            ->name('organizations.follow-ups.update');
        Route::post('cases/{case}/follow-ups/{followUp}/complete', [PatientFollowUpController::class, 'complete'])
            ->name('organizations.follow-ups.complete');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('platform/impersonation', [ImpersonationController::class, 'destroy'])
        ->name('platform.impersonation.stop');
});

Route::middleware(['auth', 'verified', EnsurePlatformAdmin::class])
    ->prefix('platform')
    ->group(function () {
        Route::get('/', PlatformDashboardController::class)->name('platform.dashboard');
        Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])
            ->name('platform.impersonation.start');
    });

require __DIR__.'/settings.php';
