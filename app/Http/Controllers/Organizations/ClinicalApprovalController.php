<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\DecideClinicalRecommendation;
use App\Enums\ClinicalApprovalOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DecideClinicalRecommendationRequest;
use App\Models\ClinicalRecommendation;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class ClinicalApprovalController extends Controller
{
    public function store(
        DecideClinicalRecommendationRequest $request,
        Team $current_team,
        PatientCase $case,
        ClinicalRecommendation $recommendation,
        DecideClinicalRecommendation $decideClinicalRecommendation,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($recommendation->patient_case_id === $case->id, 404);

        try {
            $decideClinicalRecommendation->handle(
                recommendation: $recommendation,
                approver: $request->user(),
                outcome: ClinicalApprovalOutcome::from($request->validated('outcome')),
                notes: $request->validated('notes'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['outcome' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Clinical approval decision recorded.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
