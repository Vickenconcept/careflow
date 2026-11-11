<?php

namespace App\Http\Controllers\Organizations;

use App\Enums\AppointmentStatus;
use App\Enums\TeamPermission;
use App\Enums\TravelArrangementStatus;
use App\Enums\TreatmentProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\CaseAppointment;
use App\Models\PatientCase;
use App\Models\Team;
use App\Models\TravelArrangement;
use App\Models\TreatmentProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CoordinationController extends Controller
{
    /**
     * Treatment proposals, appointments and travel across every case the coordinator can open.
     */
    public function __invoke(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [PatientCase::class, $current_team]);

        $user = $request->user();

        $tabs = collect([
            'proposals' => ['label' => 'Treatment proposals', 'permission' => TeamPermission::ManageProposals, 'case_tab' => 'proposal'],
            'appointments' => ['label' => 'Appointments', 'permission' => TeamPermission::ManageAppointments, 'case_tab' => 'appointments'],
            'travel' => ['label' => 'Travel', 'permission' => TeamPermission::ManageTravel, 'case_tab' => 'travel'],
        ])->filter(fn (array $tab, string $key) => $current_team->moduleEnabled($key)
            && $user->hasTeamPermission($current_team, $tab['permission']));

        abort_if($tabs->isEmpty(), 403);

        $active = $tabs->has($request->query('tab')) ? (string) $request->query('tab') : (string) $tabs->keys()->first();
        $status = (string) $request->query('status', '');

        $visibleCaseIds = PatientCase::query()->visibleToStaff($user, $current_team)->select('patient_cases.id');
        $caseSummary = fn (?PatientCase $case, string $caseTab) => $case === null ? null : [
            'reference' => $case->reference,
            'patient_name' => $case->patient?->fullName(),
            'url' => route('organizations.cases.show', ['current_team' => $current_team->slug, 'case' => $case->reference]).'?tab='.$caseTab,
        ];

        [$rows, $statusOptions] = match ($active) {
            'proposals' => $this->proposals($current_team, $visibleCaseIds, $status, $caseSummary, $tabs[$active]['case_tab']),
            'appointments' => $this->appointments($current_team, $visibleCaseIds, $status, $caseSummary, $tabs[$active]['case_tab']),
            default => $this->travel($current_team, $visibleCaseIds, $status, $caseSummary, $tabs[$active]['case_tab']),
        };

        return Inertia::render('organizations/coordination', [
            'team' => [
                'id' => $current_team->id,
                'name' => $current_team->name,
                'slug' => $current_team->slug,
            ],
            'tabs' => $tabs->map(fn (array $tab, string $key) => ['id' => $key, 'label' => $tab['label']])->values(),
            'active' => $active,
            'status' => $status,
            'statusOptions' => $statusOptions,
            'rows' => $rows,
        ]);
    }

    /**
     * @return array{0: mixed, 1: list<array{value: string, label: string}>}
     */
    protected function proposals(Team $team, $visibleCaseIds, string $status, callable $caseSummary, string $caseTab): array
    {
        $rows = TreatmentProposal::query()
            ->forTeam($team)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->when(TreatmentProposalStatus::tryFrom($status), fn ($query, $value) => $query->where('status', $value))
            ->with('case.patient')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TreatmentProposal $proposal) => [
                'id' => $proposal->uuid,
                'primary' => $proposal->reference ?: $proposal->proposed_procedure,
                'secondary' => $proposal->proposed_procedure,
                'status_label' => $proposal->status->label(),
                'detail' => trim(($proposal->estimated_cost !== null ? $proposal->currency.' '.number_format((float) $proposal->estimated_cost, 2) : '').($proposal->valid_until ? ' · valid until '.$proposal->valid_until->toDateString() : '')),
                'case' => $caseSummary($proposal->case, $caseTab),
            ]);

        return [$rows, $this->options(TreatmentProposalStatus::cases())];
    }

    /**
     * @return array{0: mixed, 1: list<array{value: string, label: string}>}
     */
    protected function appointments(Team $team, $visibleCaseIds, string $status, callable $caseSummary, string $caseTab): array
    {
        $rows = CaseAppointment::query()
            ->forTeam($team)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->when(AppointmentStatus::tryFrom($status), fn ($query, $value) => $query->where('status', $value))
            ->with(['case.patient', 'doctor:id,name'])
            ->orderByRaw('coalesce(confirmed_starts_at, proposed_starts_at) is null')
            ->orderByDesc('confirmed_starts_at')
            ->orderByDesc('proposed_starts_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (CaseAppointment $appointment) use ($caseSummary, $caseTab) {
                $starts = $appointment->confirmed_starts_at ?? $appointment->proposed_starts_at;

                return [
                    'id' => $appointment->uuid,
                    'primary' => $appointment->appointment_type->label(),
                    'secondary' => $appointment->doctor?->name ? 'With '.$appointment->doctor->name : null,
                    'status_label' => $appointment->status->label(),
                    'detail' => $starts ? ($appointment->confirmed_starts_at ? 'Confirmed ' : 'Proposed ').$starts->format('M j, Y H:i') : 'No date yet',
                    'case' => $caseSummary($appointment->case, $caseTab),
                ];
            });

        return [$rows, $this->options(AppointmentStatus::cases())];
    }

    /**
     * @return array{0: mixed, 1: list<array{value: string, label: string}>}
     */
    protected function travel(Team $team, $visibleCaseIds, string $status, callable $caseSummary, string $caseTab): array
    {
        $rows = TravelArrangement::query()
            ->forTeam($team)
            ->whereIn('patient_case_id', $visibleCaseIds)
            ->when(TravelArrangementStatus::tryFrom($status), fn ($query, $value) => $query->where('status', $value))
            ->with(['case.patient', 'assignedCoordinator:id,name'])
            ->orderByRaw('expected_arrival_date is null')
            ->orderBy('expected_arrival_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TravelArrangement $travel) => [
                'id' => $travel->uuid,
                'primary' => $travel->expected_arrival_date ? 'Arrives '.$travel->expected_arrival_date->toDateString() : 'Arrival date not set',
                'secondary' => $travel->assignedCoordinator?->name ? 'Coordinator: '.$travel->assignedCoordinator->name : null,
                'status_label' => $travel->status->label(),
                'detail' => collect([
                    $travel->airport_pickup_required ? 'Airport pickup' : null,
                    $travel->accommodation,
                ])->filter()->implode(' · '),
                'case' => $caseSummary($travel->case, $caseTab),
            ]);

        return [$rows, $this->options(TravelArrangementStatus::cases())];
    }

    /**
     * @param  list<\BackedEnum&object>  $cases
     * @return list<array{value: string, label: string}>
     */
    protected function options(array $cases): array
    {
        return collect($cases)->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])->values()->all();
    }
}
