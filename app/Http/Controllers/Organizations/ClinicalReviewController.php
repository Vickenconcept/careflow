<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\SubmitDoctorReview;
use App\Enums\DoctorReviewOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\SubmitDoctorReviewRequest;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class ClinicalReviewController extends Controller
{
    public function store(
        SubmitDoctorReviewRequest $request,
        Team $current_team,
        PatientCase $case,
        SubmitDoctorReview $submitDoctorReview,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $submitDoctorReview->handle(
                case: $case,
                reviewer: $request->user(),
                outcome: DoctorReviewOutcome::from($request->validated('outcome')),
                findings: $request->validated('findings'),
                additionalInformationRequest: $request->validated('additional_information_request'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['findings' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Doctor review submitted. AI content remains unapproved.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
