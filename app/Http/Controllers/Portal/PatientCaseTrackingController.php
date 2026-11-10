<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientCaseTrackingController extends Controller
{
    /**
     * Secure patient-facing case status. Never expose clinical notes or AI internals.
     */
    public function show(Request $request, Team $organization, string $reference): Response
    {
        $token = $request->string('token')->toString();

        abort_unless($token !== '', 404);

        $case = PatientCase::query()
            ->forTeam($organization)
            ->where('reference', $reference)
            ->with(['patient:id,first_name,last_name,preferred_language', 'department:id,name'])
            ->firstOrFail();

        abort_unless($case->portalAccessTokenIsValid($token), 403);

        return Inertia::render('portal/case-tracking', [
            'organization' => [
                'name' => $organization->organizationSetting?->legal_name ?: $organization->name,
                'slug' => $organization->slug,
            ],
            'case' => [
                'reference' => $case->reference,
                'status' => $case->status->patientLabel(),
                'department' => $case->department?->name,
                'patient_first_name' => $case->patient->first_name,
                'preferred_language' => $case->patient->preferred_language,
                'last_activity_at' => $case->last_activity_at?->toIso8601String(),
            ],
        ]);
    }
}
