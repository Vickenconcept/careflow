<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\SubmitPatientIntake;
use App\Data\Patients\IntakeSubmissionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreStaffCaseRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StaffCaseIntakeController extends Controller
{
    /**
     * Create a case for an enquiry that came in by phone, email, WhatsApp, a walk-in or an agent.
     *
     * It goes through the same steps as a portal enquiry (patient record, consents,
     * workflows), with the staff member recorded as the person who entered it.
     */
    public function __invoke(StoreStaffCaseRequest $request, Team $current_team, SubmitPatientIntake $submitPatientIntake): RedirectResponse
    {
        $validated = $request->validated();
        $source = StoreStaffCaseRequest::SOURCES[$validated['source']];

        $result = $submitPatientIntake->handle(IntakeSubmissionData::fromValidatedRequest(
            team: $current_team,
            validated: [
                ...$validated,
                'additional_comments' => trim('Enquiry received by: '.$source."\n".($validated['additional_comments'] ?? '')),
                'enquiry_consent' => true,
                'health_data_consent' => true,
            ],
            uploadedFiles: [],
            categories: [],
            sourceIp: $request->ip(),
            userAgent: $request->userAgent(),
            recordedBy: $request->user(),
            emailPatient: $request->boolean('email_patient', true),
        ));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Case :reference created. Add any documents the patient sent.', ['reference' => $result['case']->reference]),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $result['case']->reference,
            'tab' => 'documents',
        ]);
    }
}
