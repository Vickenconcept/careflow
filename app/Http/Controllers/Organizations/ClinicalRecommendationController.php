<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\UpsertClinicalRecommendation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpsertClinicalRecommendationRequest;
use App\Models\ClinicalRecommendation;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class ClinicalRecommendationController extends Controller
{
    public function store(
        UpsertClinicalRecommendationRequest $request,
        Team $current_team,
        PatientCase $case,
        UpsertClinicalRecommendation $upsertClinicalRecommendation,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $upsertClinicalRecommendation->handle(
                case: $case,
                author: $request->user(),
                recommendationText: $request->validated('recommendation_text'),
                supportingDocumentUuids: $request->validated('supporting_document_uuids', []),
                submitForApproval: (bool) $request->boolean('submit_for_approval'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['recommendation_text' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Clinical recommendation saved.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }

    public function update(
        UpsertClinicalRecommendationRequest $request,
        Team $current_team,
        PatientCase $case,
        ClinicalRecommendation $recommendation,
        UpsertClinicalRecommendation $upsertClinicalRecommendation,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);
        abort_unless($recommendation->patient_case_id === $case->id, 404);

        try {
            $upsertClinicalRecommendation->handle(
                case: $case,
                author: $request->user(),
                recommendationText: $request->validated('recommendation_text'),
                supportingDocumentUuids: $request->validated('supporting_document_uuids', []),
                submitForApproval: (bool) $request->boolean('submit_for_approval'),
                recommendation: $recommendation,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['recommendation_text' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Clinical recommendation updated.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
