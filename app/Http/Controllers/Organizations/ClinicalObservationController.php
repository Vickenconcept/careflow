<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\RecordClinicalObservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\StoreClinicalObservationRequest;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class ClinicalObservationController extends Controller
{
    public function store(
        StoreClinicalObservationRequest $request,
        Team $current_team,
        PatientCase $case,
        RecordClinicalObservation $recordClinicalObservation,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $recordClinicalObservation->handle(
                case: $case,
                author: $request->user(),
                body: $request->validated('body'),
                visibility: $request->validated('visibility'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['body' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Clinical observation recorded.'),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
