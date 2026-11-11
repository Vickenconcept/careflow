<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Patients\ErasePatientData;
use App\Actions\Patients\RecordCaseActivity;
use App\Enums\CaseActivityType;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientCase;
use App\Models\PatientDocument;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataToolsController extends Controller
{
    /**
     * Retention policy, patient data export and erasure for administrators.
     */
    public function show(Request $request, Team $current_team): Response
    {
        Gate::authorize('manageSettings', $current_team);

        $settings = $current_team->ensureOrganizationSetting();
        $search = trim((string) $request->query('q', ''));

        $patients = $search === '' ? collect() : Patient::query()
            ->forTeam($current_team)
            ->where(function ($query) use ($search): void {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->withCount('cases')
            ->orderBy('last_name')
            ->limit(10)
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->uuid,
                'name' => $patient->fullName(),
                'email' => $patient->email,
                'case_count' => $patient->cases_count,
            ]);

        return Inertia::render('organizations/data-tools', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'retentionMonths' => $settings->document_retention_months,
            'expiredDocumentCount' => $settings->document_retention_months === null
                ? 0
                : $this->expiredDocuments($current_team, $settings->document_retention_months)->count(),
            'search' => $search,
            'patients' => $patients,
        ]);
    }

    public function updateRetention(Request $request, Team $current_team): RedirectResponse
    {
        Gate::authorize('manageSettings', $current_team);

        $validated = $request->validate([
            'document_retention_months' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        $current_team->ensureOrganizationSetting()->update([
            'document_retention_months' => $validated['document_retention_months'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Retention policy saved.')]);

        return back();
    }

    public function purgeExpired(Request $request, Team $current_team, ErasePatientData $eraser): RedirectResponse
    {
        Gate::authorize('manageSettings', $current_team);

        $months = $current_team->ensureOrganizationSetting()->document_retention_months;

        if ($months === null) {
            return back()->withErrors(['retention' => 'Set a retention period first.']);
        }

        $count = 0;

        $this->expiredDocuments($current_team, $months)->with('case')->each(function (PatientDocument $document) use ($eraser, $request, &$count): void {
            $eraser->deleteStoredFile($document);

            if ($document->case !== null) {
                app(RecordCaseActivity::class)->handle(
                    case: $document->case,
                    type: CaseActivityType::DataRetentionAction,
                    summary: 'Document removed under the retention policy: '.$document->original_filename,
                    actor: $request->user(),
                );
            }

            $document->forceDelete();
            $count++;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('{0} No expired documents to remove.|{1} :count expired document removed.|[2,*] :count expired documents removed.', $count, ['count' => $count]),
        ]);

        return back();
    }

    public function exportPatient(Request $request, Team $current_team, Patient $patient): StreamedResponse|JsonResponse
    {
        Gate::authorize('manageSettings', $current_team);
        abort_unless($patient->team_id === $current_team->id, 404);

        $patient->load([
            'enquiries',
            'cases.activities',
            'cases.communications',
            'cases.treatmentProposals',
            'cases.appointments',
            'cases.travelArrangement',
            'cases.followUps',
            'documents',
        ]);

        foreach ($patient->cases as $case) {
            app(RecordCaseActivity::class)->handle(
                case: $case,
                type: CaseActivityType::DataRetentionAction,
                summary: 'Patient data exported',
                actor: $request->user(),
            );
        }

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'organization' => $current_team->name,
            'patient' => $patient->only(['uuid', 'first_name', 'last_name', 'date_of_birth', 'gender', 'country_of_residence', 'email', 'phone', 'preferred_language']),
            'enquiries' => $patient->enquiries->toArray(),
            'cases' => $patient->cases->map(fn (PatientCase $case) => $case->toArray())->all(),
            'documents' => $patient->documents->map(fn (PatientDocument $document) => $document->only([
                'uuid', 'original_filename', 'category', 'mime_type', 'size_bytes', 'uploaded_at',
            ]))->all(),
        ];

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            'patient-'.$patient->uuid.'-export.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function erasePatient(Request $request, Team $current_team, Patient $patient, ErasePatientData $eraser): RedirectResponse
    {
        Gate::authorize('manageSettings', $current_team);
        abort_unless($patient->team_id === $current_team->id, 404);

        $request->validate(['confirm_email' => ['required', 'string']]);

        if (strcasecmp(trim((string) $request->input('confirm_email')), $patient->email) !== 0) {
            return back()->withErrors(['confirm_email' => 'Type the patient\'s email exactly to confirm.']);
        }

        $eraser->handle($patient, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Patient data erased.')]);

        return to_route('organizations.data-tools', ['current_team' => $current_team->slug]);
    }

    /**
     * Documents on closed or declined cases that have been idle longer than the retention period.
     *
     * @return Builder<PatientDocument>
     */
    protected function expiredDocuments(Team $team, int $months)
    {
        return PatientDocument::query()
            ->forTeam($team)
            ->whereIn('patient_case_id', PatientCase::query()
                ->where('team_id', $team->id)
                ->whereIn('status', [CaseStatus::Closed, CaseStatus::Declined])
                ->where('last_activity_at', '<', now()->subMonths($months))
                ->select('id'));
    }
}
