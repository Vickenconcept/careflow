<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientDirectoryController extends Controller
{
    /**
     * Every patient the signed-in staff member may see, through the cases they can open.
     */
    public function __invoke(Request $request, Team $current_team): Response
    {
        $user = $request->user();

        abort_unless($user->hasTeamPermission($current_team, TeamPermission::ViewPatients), 403);

        $search = trim((string) $request->query('q', ''));

        $patients = Patient::query()
            ->forTeam($current_team)
            ->whereHas('cases', fn ($cases) => $cases->visibleToStaff($user, $current_team))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        $casesByPatient = PatientCase::query()
            ->visibleToStaff($user, $current_team)
            ->whereIn('patient_id', $patients->getCollection()->pluck('id'))
            ->orderByDesc('last_activity_at')
            ->get()
            ->groupBy('patient_id');

        $patients->through(function (Patient $patient) use ($casesByPatient, $current_team) {
            $cases = $casesByPatient->get($patient->id, collect());

            return [
                'id' => $patient->uuid,
                'name' => $patient->fullName(),
                'email' => $patient->email,
                'phone' => $patient->phone,
                'country' => $patient->country_of_residence,
                'language' => $patient->preferred_language,
                'case_count' => $cases->count(),
                'cases' => $cases->take(3)->map(fn (PatientCase $case) => [
                    'reference' => $case->reference,
                    'status_label' => $case->status->label(),
                    'url' => route('organizations.cases.show', ['current_team' => $current_team->slug, 'case' => $case->reference]),
                ])->values(),
                'last_activity_at' => $cases->first()?->last_activity_at?->toIso8601String(),
                'profile_url' => route('organizations.patients.show', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
                'messages_url' => $cases->first() !== null
                    ? route('organizations.cases.show', ['current_team' => $current_team->slug, 'case' => $cases->first()->reference, 'tab' => 'communications'])
                    : null,
            ];
        });

        return Inertia::render('organizations/patients', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'patients' => $patients,
            'search' => $search,
        ]);
    }
}
