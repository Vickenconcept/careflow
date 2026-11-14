<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\RecordCaseActivity;
use App\Enums\CaseActivityType;
use App\Enums\PatientGender;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdatePatientRequest;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientConsent;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PatientProfileController extends Controller
{
    /**
     * One patient: contact details, every case the staff member may open, and recorded consents.
     */
    public function show(Request $request, Team $current_team, Patient $patient): Response
    {
        $user = $request->user();
        abort_unless($patient->team_id === $current_team->id, 404);
        abort_unless($user->hasTeamPermission($current_team, TeamPermission::ViewPatients), 403);

        $cases = PatientCase::query()
            ->visibleToStaff($user, $current_team)
            ->where('patient_id', $patient->id)
            ->with(['department:id,name', 'assignedCoordinator:id,name'])
            ->withCount('documents')
            ->orderByDesc('last_activity_at')
            ->get();

        abort_if($cases->isEmpty(), 404);

        $latest = $cases->first();

        return Inertia::render('organizations/patients/show', [
            'team' => ['id' => $current_team->id, 'name' => $current_team->name, 'slug' => $current_team->slug],
            'patient' => [
                'id' => $patient->uuid,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'full_name' => $patient->fullName(),
                'email' => $patient->email,
                'phone' => $patient->phone,
                'date_of_birth' => $patient->date_of_birth?->toDateString(),
                'gender' => $patient->gender?->value,
                'gender_label' => $patient->gender?->label(),
                'country_of_residence' => $patient->country_of_residence,
                'preferred_language' => $patient->preferred_language,
                'is_demo' => $patient->is_demo,
                'created_at' => $patient->created_at?->toIso8601String(),
            ],
            'cases' => $cases->map(fn (PatientCase $case) => [
                'reference' => $case->reference,
                'status_label' => $case->status->label(),
                'department' => $case->department?->name,
                'coordinator' => $case->assignedCoordinator?->name,
                'documents_count' => $case->documents_count,
                'last_activity_at' => $case->last_activity_at?->toIso8601String(),
                'url' => route('organizations.cases.show', ['current_team' => $current_team->slug, 'case' => $case->reference]),
            ])->values(),
            'consents' => PatientConsent::query()
                ->where('patient_id', $patient->id)
                ->orderByDesc('consented_at')
                ->get()
                ->map(fn (PatientConsent $consent) => [
                    'id' => $consent->id,
                    'label' => $consent->consent_type->label(),
                    'accepted' => $consent->accepted,
                    'consented_at' => $consent->consented_at?->toIso8601String(),
                ])->values(),
            'messagesUrl' => route('organizations.cases.show', [
                'current_team' => $current_team->slug,
                'case' => $latest->reference,
                'tab' => 'communications',
            ]),
            'updateUrl' => route('organizations.patients.update', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]),
            'canEdit' => $user->hasTeamPermission($current_team, TeamPermission::ManageCases),
            'options' => [
                'genders' => collect(PatientGender::cases())
                    ->map(fn (PatientGender $gender) => ['value' => $gender->value, 'label' => $gender->label()])
                    ->values(),
                'languages' => collect(config('careflow.portal.supported_languages', ['en']))
                    ->map(fn (string $code) => ['value' => $code, 'label' => strtoupper($code)])
                    ->values(),
            ],
        ]);
    }

    /**
     * Correct a patient's details, for example a mistyped email address.
     */
    public function update(UpdatePatientRequest $request, Team $current_team, Patient $patient, RecordCaseActivity $recordActivity): RedirectResponse
    {
        abort_unless($patient->team_id === $current_team->id, 404);
        abort_unless(
            PatientCase::query()->visibleToStaff($request->user(), $current_team)->where('patient_id', $patient->id)->exists(),
            404,
        );

        $validated = $request->validated();
        $validated['email'] = strtolower((string) $validated['email']);
        $validated['country_of_residence'] = isset($validated['country_of_residence'])
            ? strtoupper((string) $validated['country_of_residence'])
            : null;

        DB::transaction(function () use ($patient, $validated, $request, $recordActivity): void {
            $patient->fill($validated);
            $changed = array_keys($patient->getDirty());

            if ($changed === []) {
                return;
            }

            $patient->save();

            $latestCase = $patient->cases()->orderByDesc('last_activity_at')->first();

            if ($latestCase !== null) {
                $recordActivity->handle(
                    case: $latestCase,
                    type: CaseActivityType::NoteAdded,
                    summary: sprintf('Patient details updated by %s', $request->user()->name),
                    actor: $request->user(),
                    properties: ['patient_uuid' => $patient->uuid, 'changed_fields' => $changed],
                );
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Patient details saved.')]);

        return to_route('organizations.patients.show', ['current_team' => $current_team->slug, 'patient' => $patient->uuid]);
    }
}
