<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\NotifyPatient;
use App\Actions\Patients\UpsertTravelArrangement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpsertTravelArrangementRequest;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

class TravelArrangementController extends Controller
{
    public function upsert(
        UpsertTravelArrangementRequest $request,
        Team $current_team,
        PatientCase $case,
        UpsertTravelArrangement $upsertTravelArrangement,
    ): RedirectResponse {
        abort_unless($case->team_id === $current_team->id, 404);

        try {
            $upsertTravelArrangement->handle(
                case: $case,
                actor: $request->user(),
                data: $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['travel' => $exception->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trim(__('Travel arrangement saved.').' '.(app(NotifyPatient::class)->staffSummary() ?? '')),
        ]);

        return to_route('organizations.cases.show', [
            'current_team' => $current_team->slug,
            'case' => $case->reference,
        ]);
    }
}
